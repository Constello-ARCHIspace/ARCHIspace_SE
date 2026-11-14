#!/usr/bin/env python3
"""Convert a Project NOAH province zip (ESRI shapefile, WGS 84) into the
hazard-data cell folder that public/hazard.php reads.

    python3 -I tools/noah_to_cells.py NOAH.zip hazard-data/flood-100 --field Var \
        --bbox 121.84,6.84,122.51,7.66 --dataset "Flood 100-year, Zamboanga del Sur" \
        --source-url https://drive.google.com/drive/folders/1ALE4-E9c-4AGjm1fqiPprWHrLUskeY9o

Fields: Var (flood), LH (landslide), HAZ (storm surge). Classes must be 1, 2, 3
(low, medium, high); anything else stops the conversion.

Output folder:
  index.json  source, licence, dataset, cell size, list of cells
  X_Y.json    one file per 0.01 degree cell (about 1.1 km), X = floor(lng/0.01),
              Y = floor(lat/0.01). Each entry is one NOAH record in that cell:
              {"l": level, "r": [[minX,minY,maxX,maxY,[x1,y1,x2,y2,...]], ...]}
              A point is inside the record if it is inside an ODD number of its
              rings (the usual shapefile reading), so holes need no special care.
Rings are clipped to their cell; coordinates are rounded to 6 decimals (~0.1 m).
A missing cell means no NOAH coverage there, never low hazard.
The .shp/.dbf/.prj are read straight from the zip; only the Python standard
library is used. Run with python3 -I.
"""
import argparse, json, math, os, struct, sys, time, zipfile
from array import array

ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
ap.add_argument('zip'); ap.add_argument('outdir')
ap.add_argument('--field', required=True)
ap.add_argument('--bbox', required=True, help='minLng,minLat,maxLng,maxLat')
ap.add_argument('--source-url', required=True)
ap.add_argument('--dataset', required=True)
ap.add_argument('--license', default='Open Data Commons Open Database License (ODbL); see NOAH_License.pdf')
ap.add_argument('--cell', type=float, default=0.01)
a = ap.parse_args()
bx0, by0, bx1, by1 = map(float, a.bbox.split(','))
if not (bx0 < bx1 and by0 < by1): ap.error('bbox must be minLng,minLat,maxLng,maxLat')
C = a.cell
if os.path.exists(os.path.join(a.outdir, 'index.json')): sys.exit(f'{a.outdir} already has data; use a new folder')

zf = zipfile.ZipFile(a.zip)
def member(ext):
    names = [n for n in zf.namelist() if n.lower().endswith(ext) and not n.startswith('__MACOSX')]
    if len(names) != 1: sys.exit(f'Expected one {ext} file in the zip, found {len(names)}')
    return names[0]
prj = zf.read(member('.prj')).decode('latin-1')
if 'WGS_1984' not in prj or 'PROJCS' in prj: sys.exit('Only WGS 84 longitude/latitude shapefiles are supported: ' + prj[:120])

dbf = zf.read(member('.dbf'))
nrec, hlen, rlen = struct.unpack('<IHH', dbf[4:12])
pos, col, off = 1, None, 32
while dbf[off] != 0x0D:
    name = dbf[off:off + 11].split(b'\0')[0].decode('latin-1'); flen = dbf[off + 16]
    if name.lower() == a.field.lower(): col = (pos, flen)
    pos += flen; off += 32
if col is None: sys.exit(f'Field {a.field} not found in the .dbf')
classes = []
for i in range(nrec):
    rec = dbf[hlen + i * rlen: hlen + (i + 1) * rlen]
    if rec[:1] == b'*': classes.append(None); continue
    raw = rec[col[0]:col[0] + col[1]].strip().decode('latin-1')
    try: v = float(raw)
    except ValueError: sys.exit(f'Record {i}: class {raw!r} is not a number')
    if v not in (1.0, 2.0, 3.0): sys.exit(f'Record {i}: class {raw!r} is not 1, 2 or 3; check the NOAH ReadMe first')
    classes.append(int(v))
del dbf

def clip(xs, ys, x0, y0, x1, y1):
    """Sutherland-Hodgman clip of a ring (without the closing point) to a rectangle."""
    pts = list(zip(xs, ys))
    for e in range(4):
        if not pts: return None
        out = []; prev = pts[-1]
        for cur in pts:
            ci = (cur[0] >= x0, cur[0] <= x1, cur[1] >= y0, cur[1] <= y1)[e]
            pi = (prev[0] >= x0, prev[0] <= x1, prev[1] >= y0, prev[1] <= y1)[e]
            if ci != pi:
                if e < 2:
                    xv = x0 if e == 0 else x1; t = (xv - prev[0]) / (cur[0] - prev[0]); out.append((xv, prev[1] + t * (cur[1] - prev[1])))
                else:
                    yv = y0 if e == 2 else y1; t = (yv - prev[1]) / (cur[1] - prev[1]); out.append((prev[0] + t * (cur[0] - prev[0]), yv))
            if ci: out.append(cur)
            prev = cur
        pts = out
    return pts if len(pts) >= 3 else None

shp = zf.open(member('.shp'))
head = shp.read(100)
if struct.unpack('>I', head[:4])[0] != 9994: sys.exit('Not a shapefile')
if struct.unpack('<I', head[32:36])[0] not in (5, 15, 25): sys.exit('Not a polygon shapefile')
cells = {}  # (ix, iy) -> {record: [ring, ...]}
t0 = time.time(); rec_i = -1; nrings = 0
while True:
    rh = shp.read(8)
    if len(rh) < 8: break
    rec_i += 1
    body = shp.read(struct.unpack('>I', rh[4:8])[0] * 2)
    if struct.unpack('<i', body[:4])[0] == 0 or classes[rec_i] is None: continue
    xmin, ymin, xmax, ymax = struct.unpack('<4d', body[4:36])
    if xmax < bx0 or xmin > bx1 or ymax < by0 or ymin > by1: continue
    nparts, npts = struct.unpack('<2i', body[36:44])
    parts = list(struct.unpack(f'<{nparts}i', body[44:44 + 4 * nparts])) + [npts]
    pts = array('d'); pts.frombytes(body[44 + 4 * nparts: 44 + 4 * nparts + 16 * npts])
    if sys.byteorder != 'little': pts.byteswap()
    del body
    for p in range(nparts):
        xs = pts[2 * parts[p]: 2 * parts[p + 1]: 2]; ys = pts[2 * parts[p] + 1: 2 * parts[p + 1]: 2]
        if len(xs) < 4: continue
        if xs[0] == xs[-1] and ys[0] == ys[-1]: xs, ys = xs[:-1], ys[:-1]
        rx0, ry0, rx1, ry1 = min(xs), min(ys), max(xs), max(ys)
        if rx1 < bx0 or rx0 > bx1 or ry1 < by0 or ry0 > by1: continue
        nrings += 1
        for ix in range(math.floor(rx0 / C), math.floor(rx1 / C) + 1):
            for iy in range(math.floor(ry0 / C), math.floor(ry1 / C) + 1):
                cx0, cy0 = ix * C, iy * C; cx1, cy1 = cx0 + C, cy0 + C
                if rx0 >= cx0 and ry0 >= cy0 and rx1 <= cx1 and ry1 <= cy1: ring = list(zip(xs, ys))
                else: ring = clip(xs, ys, cx0, cy0, cx1, cy1)
                if not ring: continue
                flat = []
                for x, y in ring: flat += (round(x, 6), round(y, 6))
                fx, fy = flat[0::2], flat[1::2]
                cells.setdefault((ix, iy), {}).setdefault(rec_i, []).append([min(fx), min(fy), max(fx), max(fy), flat])
    del pts

os.makedirs(a.outdir, exist_ok=True)
size = 0; largest = 0
for (ix, iy), recs in cells.items():
    path = os.path.join(a.outdir, f'{ix}_{iy}.json')
    with open(path, 'w', encoding='utf-8') as fh:
        json.dump({'f': [{'l': classes[r], 'r': rings} for r, rings in sorted(recs.items())]}, fh, separators=(',', ':'))
    s = os.path.getsize(path); size += s; largest = max(largest, s)
meta = {'format': 'archispace-hazard-cells-1', 'rule': 'even-odd per record', 'cell_deg': C,
        'attribution': 'Project NOAH and contributors', 'source_url': a.source_url, 'license': a.license,
        'dataset': a.dataset, 'source_file': os.path.basename(a.zip), 'clip_bbox': [bx0, by0, bx1, by1],
        'levels': {'1': 'low', '2': 'medium', '3': 'high'}, 'converted': time.strftime('%Y-%m-%d'),
        'cells': sorted(f'{ix}_{iy}' for ix, iy in cells)}
with open(os.path.join(a.outdir, 'index.json'), 'w', encoding='utf-8') as fh:
    json.dump(meta, fh, separators=(',', ':'))
print(f'{a.outdir}: {nrings} rings in {len(cells)} cells, {size/1e6:.1f} MB, largest cell {largest/1e6:.2f} MB, {time.time()-t0:.0f}s')
