# County/Region Progress Checker plan

## Goal

Show progress toward county or region coverage based on the user's found caches.

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

## Processing model

1. Parse uploaded GPX files.
2. Build cache index keyed by GC code:
   - lat/lon
   - cache name/url
   - found-date evidence (from logs)
3. Keep only caches with clear Found it evidence.
4. Point-in-polygon each found coordinate against county boundaries.
5. Aggregate results:
   - counties found
   - counties missing
   - grouped by state
6. Render summary and export CSV:
   - state, county, found_count, first_found_date, sample_gc

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

Phase 2 outputs:

- Leaflet map view that colors visited counties (green) and unvisited counties (light gray).
- Total counter: visited counties out of 3,143 (US total).

## Accuracy and limitations

- Coordinates in GPX are usually posted coordinates; county assignment is usually correct but may be off near borders.
- Incomplete log history can hide some valid finds.
- County names vary (Saint vs St., punctuation); normalize names and keep canonical IDs (FIPS when available).

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
- Add one new tool page (gpxcounty-progress.php) and nav + home card link.
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
- Optional SQLite for county lookup caching.
- Optional Nominatim reverse geocoding fallback only.

Implementation direction in this repository:

- Keep existing app entry points and shared layout patterns.
- Add `gpxcounty-progress.php` as the tool page.
- Add supporting helpers under `includes/` if needed (county lookup, CSV parser, cache helpers).
- `gpx...` naming is for top-level tool pages; helper files in `includes/` can follow existing helper naming conventions.
- Add local data files under a dedicated data directory (for county GeoJSON and optional SQLite db).

Operational notes:

- If Nominatim fallback is enabled, send a custom User-Agent and throttle to 1 request/second.
- Match visited counties by FIPS against map GeoJSON properties.
- Keep local dev simple via `php -S localhost:8000`.