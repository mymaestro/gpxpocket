# County/Region Progress Checker plan

## Current status (March 2026)

Implemented:

- County progress page is live and stable.
- DeLorme Atlas page progress page is live and stable.
- Shared GPX found-log parsing helper is in place for both pipelines.
- Memory-safe DeLorme matching is in place for large "My Finds" uploads (tested with ~39k unique finds).

Current pages:

- `gpxcounty-progress.php`
- `gpxdelorme-progress.php`

## Goal

Show progress toward county or region coverage based on the user's found caches.

In practice this now includes:

- US county coverage progress.
- DeLorme Atlas & Gazetteer page coverage progress.

## Core reality

An area-based Pocket Query is not enough for full county progress. This feature needs:

1. A complete or near-complete set of the user's finds.
2. A reliable county boundary dataset for point-in-polygon lookups.

## Data inputs

### Finds input (required)

Supported upload formats for MVP:

- One or more GPX files that include user-found caches.
- ZIP bundles containing GPX files.

Supported upload formats after MVP:

- CSV exports containing coordinates (Latitude/Longitude or lat/lon headers).

Recommended user workflow:

- Primary path: create a custom Pocket Query named "My Finds" on geocaching.com and export it as GPX/ZIP.
- Upload the latest "My Finds" export directly to build county/region progress.
- Fallback path: if one export is incomplete, upload multiple historical GPX batches and merge.
- De-duplicate merged caches by cache code and keep earliest found date seen for that cache.

Important note:

- If a GPX does not include a Found it log by the current user in available logs, treat find status as unknown for that cache.

Why "My Finds" is preferred:

- It is the closest source to an all-finds dataset from Pocket Queries.
- It reduces dependence on recent-log windows in area-based PQ exports.
- It simplifies upload UX to a single recurring file for most users.

### County/region dataset (required)

Preferred for US county challenge use:

- Local GeoJSON boundaries for US counties (with state + county names and FIPS).

Recommended source for MVP (developer-friendly):

- County geometry source for lookup: pinned local GeoJSON (Census-derived dataset recommended).
- County geometry source for map rendering: https://raw.githubusercontent.com/plotly/datasets/master/geojson-counties-fips.json
- Keep county matching keyed by FIPS identifiers.

Source caveats:

- Many hosted files are derived from older Census vintages; county names/boundaries can drift over time.
- Pin the exact source file/version in repo docs so results are reproducible.
- Add an update path to newer Census cartographic boundary files when needed.
- Keep county matching keyed by FIPS where available, not name text alone.

Why local data:

- Fast repeated lookups.
- No external rate limits.
- Better privacy (coordinates never leave server).

Fallback option:

- Online reverse geocoding (Nominatim) only as a backup when local lookup is unavailable.
- If fallback is enabled, respect 1 request/second and set a custom User-Agent.

### DeLorme dataset (implemented)

DeLorme page matching uses local JSON data under `data/`:

- `data/delorme-pages.json`: lightweight index with page metadata + bounding boxes.
- `data/delorme/*.json`: per-book polygon files (loaded on demand).

Design reason:

- Avoid loading all DeLorme polygons into memory at once.
- Keep request memory stable under common 128 MB PHP limits.

## Processing model

1. Parse uploaded GPX files.
2. Build cache index keyed by GC code:
   - lat/lon
   - cache name/url
   - found-date evidence (from logs)
3. Keep only caches with clear Found it evidence.
4. Resolve each found coordinate against the selected geometry dataset:
   - counties: county polygons
   - DeLorme: atlas page polygons
5. Aggregate results:
   - counties found
   - counties missing
   - grouped by state
6. Render summary and export CSV:
   - state, county, found_count, first_found_date, sample_gc

DeLorme memory-safe matching strategy (implemented):

- Group index pages by book.
- Process one book at a time.
- Load one book polygon file, match remaining finds, then release memory before next book.

Optional cache layer:

- Persist resolved coordinate-to-county mappings in SQLite to speed repeated uploads.

## User-visible outputs

- Coverage summary cards:
  - counties found
  - counties missing
  - percent complete
- Table of counties:
  - found/missing status
  - first found date
  - example cache
- Missing-only view for challenge planning.

DeLorme page outputs:

- Coverage summary cards (pages found/missing/percent).
- Table of pages:
   - found/missing status
   - state
   - book
   - page
   - first found date
   - find count
   - sample cache
- CSV export of page progress.

Phase 2 outputs:

- Leaflet map view that colors visited counties (green) and unvisited counties (light gray).
- Total counter: visited counties out of 3,143 (US total).

## Accuracy and limitations

- Coordinates in GPX are usually posted coordinates; assignment is usually correct but may be off near borders.
- Incomplete log history can hide some valid finds.
- County names vary (Saint vs St., punctuation); normalize names and keep canonical IDs (FIPS when available).
- DeLorme polygons are atlas-derived representations and may not match county geometry exactly.

## Privacy

- Keep processing local to server.
- Do not persist uploaded GPX unless explicitly enabled.
- Clean temporary files after processing.

## MVP scope

Phase 1 (MVP):

- US counties only.
- Upload one "My Finds" GPX/ZIP (with optional additional files for merge).
- Detect Found it in logs.
- Local boundary lookup as primary resolver.
- Summary + table + CSV export.
- Integrate with existing app navigation/layout.

Delivered after MVP planning:

- DeLorme page progress page.
- Shared parser helper for county + DeLorme pages.
- Optional debug diagnostics via `?debug=1` on POST results:
   - elapsed time
   - current memory
   - peak memory
   - key counters

Phase 2:

- Leaflet county map page/view.
- SQLite cache for resolved coordinate-to-county mappings.
- Optional Nominatim fallback path.
- CSV input support.

Phase 3:

- Region presets and challenge templates.
- User profile/alias matching for Found it detection.
- Progress over time chart.
- Optional saved snapshots.

## Technical fit with current codebase

- Reuse upload normalization and GPX parsing patterns from existing pages.
- Tool pages in production:
   - `gpxcounty-progress.php`
   - `gpxdelorme-progress.php`
- Keep style consistent with Bootstrap card/table patterns already used.
- Do not replace existing routing with standalone /process.php workflow; keep page integrated with current multi-tool app.

## Open decisions

1. Primary audience:
   - US counties first for MVP (recommended), multi-country later.
2. Find detection rule:
   - Strict username match (recommended default) vs permissive mode toggle.
3. Storage:
   - Stateless per upload for MVP; optional SQLite cache in Phase 2.
4. CSV behavior:
   - Treat CSV rows as already-confirmed finds by default, or require an explicit found-status column.

## Architecture notes (unified)

Stack:

- PHP backend (no framework required).
- Leaflet.js frontend for county map rendering.
- Local GeoJSON county boundaries for primary county resolution and map overlays.
- Local DeLorme page index + per-book polygon JSON files for atlas page resolution.
- Optional SQLite for county lookup caching.
- Optional Nominatim reverse geocoding fallback only.

Implementation direction in this repository:

- Keep existing app entry points and shared layout patterns.
- Add `gpxcounty-progress.php` and `gpxdelorme-progress.php` as tool pages.
- Supporting helpers currently include county lookup, DeLorme lookup, and shared finds parser.
- `gpx...` naming is for top-level tool pages; helper files in `includes/` can follow existing helper naming conventions.
- Keep local data files under `data/` (county GeoJSON, DeLorme index, DeLorme per-book polygons, optional SQLite db).

Operational notes:

- If Nominatim fallback is enabled, send a custom User-Agent and throttle to 1 request/second.
- Match visited counties by FIPS against map GeoJSON properties.
- For DeLorme matching, prefer bounded-memory book-by-book processing over global candidate expansion.
- Keep local dev simple via `php -S localhost:8000`.