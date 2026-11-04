# ARCHIspace module upgrades

Keep `index.php`, `blueprint.php` and `hazard.php` together. Requires PHP 8.1+ with sessions and file uploads enabled. Three.js, PDF.js and fonts retain the original app's external CDN dependencies.

## Run

```sh
php -S localhost:8000 -t .
```

Open http://localhost:8000/index.php. For large blueprints, configure `upload_max_filesize=12M` and `post_max_size=14M` or higher. The UI limits source files to 10 MB each.

Set `ARCHISPACE_BLUEPRINT_DIR` to a writable, persistent directory outside the web root. Otherwise PHP uses its temporary directory. Images are raster PNG files served through a session-protected endpoint; filenames are random, POST requires a CSRF token, and each session has a 100 MB quota. Project metadata and design versions remain in browser storage. Existing inline images migrate when you click Save.

**Storage limitation:** this app has no account system. Images belong to the PHP session, so access depends on retaining its cookie and server session. JSON exports contain image references, not portable image copies. Before production deployment, connect image ownership to authenticated project IDs, implement retention/deletion, and back up persistent storage. Removing an overlay does not delete its stored image, so undo can still restore it.

## Blueprint Upload

- Multiple selected/dropped files are queued. Configure and confirm each file; Close skips it and advances the queue.
- PDFs offer a page selector. Import a PDF again to add another page as another overlay.
- Click **Calibrate**, click two reference points, enter their distance in meters, then **Set scale**. Numbered markers show the points. Calibration locks the visual scale slider to 1×.
- Lot blueprints require calibration before confirmation. The calibrated image extent creates a rectangular boundary by default. Use **Mark lot boundary** and click at least three corners in order for an irregular lot. Image margins are included in the default rectangle; mark the real boundary when needed. The plot is the boundary's enclosing rectangle about the project origin. Boundary lines appear in 2D and 3D; the boundary does not clip terrain or enforce setbacks/buildable area.
- House blueprints overlay the plan and do not resize the plot. Offsets and rotation remain available.
- Images are rasterized before upload, including SVG and PDF pages. Stored images, not base64 strings, are referenced by new project state.

## Suggestions, versions and viewer

The existing recommendation rule chooses among three templates using image aspect ratio and line density, then fits the layout to the plot. It does not recognize rooms, structural members or dimensions. The UI discloses this.

Both comparison cards show the same heuristic readiness score. Applying a suggestion stores the previous layout and proposed layout separately. Select a version in Build and restore it as another new version. Versions snapshot rooms, fixtures and roof; the site and environmental scenario stay current. Undo/redo remains available.

The main workspace has **Ghost suggestion** and **Split plans** comparison modes. Generate a suggestion using the ✦ button on an uploaded blueprint first. Ghost shows cyan room volumes over the current 3D model, or cyan outlines over the floor plan. Split retains the current main view and shows a suggested 2D plan alongside it. Existing 3D / Floor plan / Top buttons switch the main view. The proposed 3D volumes are schematic; fixtures and a detailed proposed roof are not ghosted.

## Readiness Report

Environment → Readiness Report assesses:

- Terrain: nine samples per room, maximum sampled local slope, modeled river distance, terrain elevation range and floor clearance above the selected flood plane.
- Structure: wall height, selected roof, footprint regularity (sampled occupancy below 75% of the bounding rectangle), garage/large-door flag, and floor clearance.
- Floor plan: usable modeled doors at least 0.75 m wide, exterior-exit count, a room-to-room door graph, rooms without modeled windows, hallways below a 1.2 m screening target, and bedrooms below the flood plane.

Terrain, structure and plan each receive 0–100 scores; overall readiness is their mean, with 0 for no house. Risk thresholds are heuristic: Low below 30, Medium 30–59, High 60+. These are illustrative design flags, not validated hazard probabilities or code compliance thresholds. The full formulas are in `assessReadiness()` in `index.php`.

Flood and landslide ratings combine model features with available map classes. Storm surge is **Unknown** without a matching map. Wind and earthquake ratings screen design features only. The report lists a fix for each hazard and room route results. Doors shared by two room walls connect those rooms; a door touching only one room is treated as exterior. Connectivity does not assess furniture blockage, door swings, stairs, travel distance, structural materials or construction quality.

**The model terrain is procedural, not a surveyed elevation model.** NOAH flood depths are applied as an illustrative depth above the model's ground datum. Do not combine it with surveyed elevations without reconciling vertical datums. The raised-floor control changes modeled floor levels and report clearance. The screening margin of 0.3 m is an illustrative target, not a prescribed local design allowance.

Keep the original disclaimer: this is a screening and visual study tool; it does not replace an engineer's assessment or certify safety.

## Hazard Data installation

No NOAH datasets or PHIVOLCS layers are bundled. Until an administrator installs layers, the endpoint returns **unavailable**. It does not call an assumed public NOAH API and does not invent map results.

Download appropriate NOAH layers, retain the actual dataset's license and metadata, and credit **Project NOAH and contributors**. Export source shapefiles/raster classifications as WGS84 longitude/latitude GeoJSON polygons with GIS tools first. Do not rename raster/PMTiles files to GeoJSON. Verify source class meanings rather than assuming the source attribute is already normalized.

The included preprocessing helper standardizes a class field and adds feature bounding boxes. Example, for a source whose `Var` field has been verified to use classes 0–3:

```sh
python3 tools/prepare_noah.py source.geojson hazard-data/flood-100.geojson \
  --field Var --source-url 'https://noah.up.edu.ph/' --license 'ODbL-1.0'
```

Use `--map '{"Low":1,"Moderate":2,"High":3}'` for a corresponding verified string classification. Preserve any real `depth_m` property in flood features. Use the license applicable to the downloaded source; retain its license text beside the layer.

Install these files in `hazard-data/` or set `ARCHISPACE_HAZARD_DIR` to their directory:

| File | Meaning |
| --- | --- |
| flood-5.geojson | 5-year rainfall return scenario |
| flood-25.geojson | 25-year rainfall return scenario |
| flood-100.geojson | 100-year rainfall return scenario |
| landslide.geojson | Landslide susceptibility |
| surge-1.geojson through surge-4.geojson | Four distinct storm surge scenarios |

Each file must be a GeoJSON FeatureCollection with Polygon or MultiPolygon features and integer `properties.level`: 0 = explicitly mapped negligible, 1 = low, 2 = medium, 3 = high. Normalize storm-surge severity **within each scenario** using the source metadata; do not confuse scenario numbers with hazard classes. Flood may have nonnegative numeric `properties.depth_m`.

A polygon not found at the requested point means `no_coverage`, not low risk. Only explicit level-0 polygons yield a negligible result. Polygon holes are respected; the highest matching class wins in overlapping polygons. The local files are the server cache; use regional preprocessing/tiling or a spatial database for very large national layers. This initial endpoint loads each selected GeoJSON file per request.

Request:

```text
hazard.php?lat=14.65&lng=121.07&period=100&surge=4
```

The response contains per-layer status, level, optional flood depth, cached dataset date, location, scenario and credit. A matching flood class updates the flood control: level 1 uses 0.5 m, level 2 uses 1.5 m, and level 3 uses **1.6 m only as a lower-bound visual scenario** unless a depth is supplied. Level 3 means above 1.5 m, with no upper bound; 1.6 m is not a predicted or safe design flood depth. The risk report retains a High flood hazard for level 3 even after raising the modeled floor.

Changing location/scenario clears the previous result. Failed lookups and unmatched/missing layers remain unavailable; the flood slider can still be used as an explicitly selected model scenario.

NOAH's hazard-check page describes beta, single-point screening. Coverage varies; do not assume complete 100-year flood coverage for Tawi-Tawi. Earthquake exposure requires a separate PHIVOLCS source, including active faults and liquefaction. This package provides the source link and makes its absence explicit; it does not claim PHIVOLCS integration.

Sources to consult:
- https://noah.up.edu.ph/know-your-hazards
- https://noah.up.edu.ph/
- https://hazardhunter.georisk.gov.ph/

## Verification

Executed in the build environment:

```sh
node --check extracted-app.js
node tests/readiness.test.cjs
```

The test suite covers unconnected rooms, shared doors versus exterior exits, flood-exposed bedrooms, raised floors, missing hazard data, matching high classes, irregular footprints, narrow hallways and calibrated/rotated lot dimensions. Preprocessing was also exercised with a valid layer and rejected out-of-range coordinates.

PHP and a browser executable were unavailable in the build environment, so PHP lint, live HTTP upload/lookup tests and visual browser verification remain to run locally:

```sh
php -l index.php
php -l blueprint.php
php -l hazard.php
```

Then verify: batch imports and skipping files; PDF page changes; two-point scale on a known scan; irregular lot corners; house upload leaves plot dimensions unchanged; PNG upload returns an endpoint URL; image persists after reload in the same session; version restore preserves current site; ghost view in 2D/3D; empty/missing/overlapping/holed GeoJSON layers; high flood class remains high after floor raising; and changed coordinates invalidate the old result.
#   A R C H I s p a c e _ S E  
 