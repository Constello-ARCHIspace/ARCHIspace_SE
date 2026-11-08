#!/usr/bin/env python3
"""Normalize a downloaded NOAH WGS84 GeoJSON layer, preserving source metadata.
Example: python tools/prepare_noah.py input.geojson hazard-data/flood-100.geojson --field Var --source-url URL --license ODbL-1.0
Use GIS software to export shapefiles in EPSG:4326 first. Never infer level 0
from absence of a polygon. Only use level 0 when explicitly present in source.
"""
import argparse,json,math
from pathlib import Path
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('input');p.add_argument('output');p.add_argument('--field',required=True)
p.add_argument('--map',default='{"0":0,"1":1,"2":2,"3":3}',help='JSON mapping of source classes to 0–3')
p.add_argument('--source-url',required=True);p.add_argument('--license',required=True)
a=p.parse_args();mapping=json.loads(a.map);data=json.loads(Path(a.input).read_text())
if data.get('type')!='FeatureCollection':p.error('Expected GeoJSON FeatureCollection')
def coords(c):
    if c and isinstance(c[0],(float,int)):
        if len(c)<2 or not all(math.isfinite(v) for v in c[:2]) or not -180<=c[0]<=180 or not -90<=c[1]<=90:raise ValueError('Coordinates must be WGS84 longitude,latitude')
        yield c
    else:
        for v in c:yield from coords(v)
for f in data['features']:
    g=f.get('geometry') or {}
    if g.get('type') not in ('Polygon','MultiPolygon'):raise ValueError('Only Polygon and MultiPolygon supported')
    ps=list(coords(g['coordinates']))
    if not ps:raise ValueError('Empty geometry')
    key=str(f['properties'][a.field]);level=mapping.get(key)
    if type(level)!=int or level not in range(4):raise ValueError(f'Unmapped class: {key}')
    f['properties']['level']=level
    f['bbox']=[min(q[0] for q in ps),min(q[1] for q in ps),max(q[0] for q in ps),max(q[1] for q in ps)]
data['attribution']='Project NOAH and contributors';data['source_url']=a.source_url;data['license']=a.license
out=Path(a.output);out.parent.mkdir(parents=True,exist_ok=True);out.write_text(json.dumps(data,separators=(',',':')))
print(f'Prepared {len(data["features"])} features: {out}')
