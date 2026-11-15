#!/usr/bin/env python3
"""Convert Copernicus DEM GLO-30 tiles (COG GeoTIFF) into the elevation cells
that public/elevation.php reads.

    python3 -I tools/dem_to_cells.py hazard-data/elevation --bbox 121.84,6.84,122.51,7.66 \
        Copernicus_DSM_COG_10_N06_00_E122_00_DEM.tif  Copernicus_DSM_COG_10_N07_00_E122_00_DEM.tif ...

Give every 1 degree tile that touches the bbox (tiles are named by their
south-west corner: N06_00_E122_00 covers lat 6-7, lng 122-123). Download them
from the official AWS Open Data mirror of the Copernicus DEM:
https://copernicus-dem-30m.s3.amazonaws.com/<tile>/<tile>.tif

Output folder:
  index.json   format, cell size, attribution, licence, bbox, list of cells
  X_Y.json     one file per 0.01 degree cell, X = floor(lng/0.01), Y = floor(lat/0.01):
               {"h":[...]} = 36 x 36 heights in decimetres above the EGM2008 geoid,
               row by row from the SOUTH edge, each row from the WEST edge; sample
               (i, j) is at lat = Y*0.01 + i/3600, lng = X*0.01 + j/3600. These are
               the native 1 arc-second samples (pixel-is-point), nothing resampled.
A cell is written only when all 36 x 36 samples are available.

Supported GeoTIFF: little- or big-endian classic TIFF, one 32-bit float band,
tiled or stripped, uncompressed or Deflate (8/32946), predictor 1 or 3.
Only the Python standard library is used. Run with python3 -I.
"""
import argparse, json, math, os, struct, sys, time, zlib
from array import array
from itertools import accumulate

ARCSEC = 3600  # samples per degree

def read_tiff(path):
    d = open(path, 'rb').read()
    if d[:2] == b'II': bo = '<'
    elif d[:2] == b'MM': bo = '>'
    else: sys.exit(f'{path}: not a TIFF')
    if struct.unpack(bo + 'H', d[2:4])[0] != 42: sys.exit(f'{path}: BigTIFF is not supported')
    off = struct.unpack(bo + 'I', d[4:8])[0]
    n = struct.unpack(bo + 'H', d[off:off + 2])[0]
    sizes = {1: 1, 2: 1, 3: 2, 4: 4, 5: 8, 6: 1, 7: 1, 8: 2, 9: 4, 10: 8, 11: 4, 12: 8}
    fmts = {1: 'B', 3: 'H', 4: 'I', 8: 'h', 9: 'i', 11: 'f', 12: 'd', 2: 'c', 6: 'b', 7: 'B'}
    tags = {}
    for i in range(n):
        e = d[off + 2 + i * 12: off + 14 + i * 12]
        tag, typ, cnt = struct.unpack(bo + 'HHI', e[:8])
        size = sizes.get(typ, 1) * cnt
        raw = e[8:8 + size] if size <= 4 else d[struct.unpack(bo + 'I', e[8:12])[0]:][:size]
        if typ in fmts and typ != 2: tags[tag] = struct.unpack(bo + str(cnt) + fmts[typ], raw)
        else: tags[tag] = raw
    g = lambda t, default=None: tags[t][0] if t in tags else default
    W, H = g(256), g(257)
    if g(277, 1) != 1 or g(258) != 32 or g(339, 1) != 3: sys.exit(f'{path}: expected one 32-bit float band')
    comp, pred = g(259, 1), g(317, 1)
    if comp not in (1, 8, 32946): sys.exit(f'{path}: compression {comp} is not supported (only none or Deflate)')
    if pred not in (1, 3): sys.exit(f'{path}: predictor {pred} is not supported')
    if 322 in tags: tw, th, offs, counts = g(322), g(323), tags[324], tags[325]
    else: tw, th, offs, counts = W, g(278, H), tags[273], tags[279]
    scale = tags.get(33550); tie = tags.get(33922)
    if not scale or not tie: sys.exit(f'{path}: no georeferencing tags')
    sx, sy = scale[0], scale[1]
    if abs(sx * ARCSEC - 1) > 1e-6 or abs(sy * ARCSEC - 1) > 1e-6: sys.exit(f'{path}: expected a 1 arc-second grid, got {sx} x {sy} degrees')
    gk = tags.get(34735, ())
    raster_type = dict(zip(gk[4::4], gk[7::4])).get(1025, 1) if gk else 1
    x0, ytop = tie[3], tie[4]
    if raster_type == 1: x0 += sx / 2; ytop -= sy / 2  # pixel-is-area: move to the sample centres
    # whole arc-second indices of the top-left sample
    lng0 = round(x0 * ARCSEC); lat_top = round(ytop * ARCSEC)
    if abs(lng0 - x0 * ARCSEC) > 1e-3 or abs(lat_top - ytop * ARCSEC) > 1e-3: sys.exit(f'{path}: grid is not aligned to whole arc-seconds')
    return {'bytes': d, 'bo': bo, 'W': W, 'H': H, 'tw': tw, 'th': th, 'offs': offs, 'counts': counts,
            'comp': comp, 'pred': pred, 'lng0': lng0, 'lat_top': lat_top, 'tiles': {}, 'path': path}

def decode_tile(t, k):
    """Return the k-th tile as a flat list of floats (th rows of tw)."""
    if k in t['tiles']: return t['tiles'][k]
    raw = t['bytes'][t['offs'][k]: t['offs'][k] + t['counts'][k]]
    if t['comp'] != 1: raw = zlib.decompress(raw)
    tw, th = t['tw'], t['th']
    row_bytes = tw * 4
    if len(raw) < row_bytes * th: raw = raw + bytes(row_bytes * th - len(raw))
    vals = array('f')
    if t['pred'] == 3:
        for r in range(th):
            row = list(accumulate(raw[r * row_bytes:(r + 1) * row_bytes], lambda a, b: (a + b) & 255))
            be = bytes(x for quad in zip(row[0:tw], row[tw:2 * tw], row[2 * tw:3 * tw], row[3 * tw:4 * tw]) for x in quad)
            vals.extend(struct.unpack('>%df' % tw, be))
    else:
        vals.frombytes(raw[:row_bytes * th])
        if (sys.byteorder == 'little') != (t['bo'] == '<'): vals.byteswap()
    t['tiles'][k] = vals
    return vals

def sample(t, lat_as, lng_as):
    """Height at whole arc-second (lat, lng), or None when outside this file."""
    r = t['lat_top'] - lat_as; c = lng_as - t['lng0']
    if r < 0 or c < 0 or r >= t['H'] or c >= t['W']: return None
    tx = (t['W'] + t['tw'] - 1) // t['tw']
    k = (r // t['th']) * tx + c // t['tw']
    vals = decode_tile(t, k)
    return vals[(r % t['th']) * t['tw'] + (c % t['tw'])]

ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
ap.add_argument('outdir'); ap.add_argument('tif', nargs='+')
ap.add_argument('--bbox', required=True, help='minLng,minLat,maxLng,maxLat')
ap.add_argument('--cell', type=float, default=0.01)
a = ap.parse_args()
bx0, by0, bx1, by1 = map(float, a.bbox.split(','))
if not (bx0 < bx1 and by0 < by1): ap.error('bbox must be minLng,minLat,maxLng,maxLat')
C = a.cell; n_per = round(C * ARCSEC)
if abs(n_per - C * ARCSEC) > 1e-6: ap.error('cell size must be a whole number of arc-seconds')
if os.path.exists(os.path.join(a.outdir, 'index.json')): sys.exit(f'{a.outdir} already has data; use a new folder')
files = [read_tiff(p) for p in a.tif]
t0 = time.time(); os.makedirs(a.outdir, exist_ok=True)
cells = []; skipped = 0; lo = hi = None
for X in range(math.floor(bx0 / C), math.floor(bx1 / C) + 1):
    for Y in range(math.floor(by0 / C), math.floor(by1 / C) + 1):
        h = []
        ok = True
        for i in range(n_per):
            lat_as = Y * n_per + i
            for j in range(n_per):
                lng_as = X * n_per + j
                v = None
                for f in files:
                    v = sample(f, lat_as, lng_as)
                    if v is not None: break
                if v is None or v != v: ok = False; break
                h.append(round(v * 10))
            if not ok: break
        if not ok: skipped += 1; continue
        with open(os.path.join(a.outdir, f'{X}_{Y}.json'), 'w', encoding='utf-8') as fh:
            json.dump({'h': h}, fh, separators=(',', ':'))
        cells.append(f'{X}_{Y}')
        mn, mx = min(h), max(h); lo = mn if lo is None else min(lo, mn); hi = mx if hi is None else max(hi, mx)
meta = {'format': 'archispace-elevation-cells-1', 'cell_deg': C, 'n': n_per, 'unit': 'decimetres above the EGM2008 geoid',
        'order': 'rows from the south edge, columns from the west edge',
        'source': 'Copernicus DEM GLO-30, via the AWS Open Data mirror (copernicus-dem-30m)',
        'attribution': '© DLR e.V. 2010-2014 and © Airbus Defence and Space GmbH 2014-2018 provided under COPERNICUS by the European Union and ESA; all rights reserved',
        'license': 'Copernicus DEM licence (free use with the attribution above); see https://spacedata.copernicus.eu',
        'source_files': [os.path.basename(f['path']) for f in files], 'clip_bbox': [bx0, by0, bx1, by1],
        'converted': time.strftime('%Y-%m-%d'), 'cells': sorted(cells)}
with open(os.path.join(a.outdir, 'index.json'), 'w', encoding='utf-8') as fh: json.dump(meta, fh, separators=(',', ':'))
print(f'{a.outdir}: {len(cells)} cells written, {skipped} skipped (no data), heights {lo/10 if lo is not None else "-"} to {hi/10 if hi is not None else "-"} m, {time.time()-t0:.0f}s')
