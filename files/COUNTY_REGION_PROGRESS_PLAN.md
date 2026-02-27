# County/Region Progress Checker plan

## Goal

Show progress toward county or region coverage based on the user's found caches.

## Core reality

A Pocket Query around an area is not enough for full county progress. This feature needs:

1. A complete or near-complete set of the user's finds.
2. A reliable county/region boundary dataset for point-in-polygon lookups.

## Data inputs

### Finds input (required)

Supported upload formats for MVP:

- One or more GPX files that include user-found caches.
- ZIP bundles containing GPX files.

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

- Direct GeoJSON from eric.clst.org: https://eric.clst.org/tech/usgeojson/
- Use county files that already include FIPS-coded county identifiers.
- Start with a medium resolution file (for example 5m) to balance precision and speed.

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

- Online reverse geocoding (GeoNames/Nominatim) only as a backup when local dataset is missing.
- If direct GeoJSON quality is insufficient for a state/region, regenerate from current Census boundary files.

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
   - grouped by state/region
6. Render summary and export CSV:
   - state, county, found_count, first_found_date, sample_gc

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
- Local boundary lookup.
- Summary + table + CSV export.

Phase 2:

- Region presets (states, provinces, countries).
- User profile/alias matching for Found it detection.
- Progress over time chart.

Phase 3:

- Challenge templates (all counties in state, all counties in region set).
- Import/export saved progress snapshots.

## Technical fit with current codebase

- Reuse upload normalization and GPX parsing patterns from existing pages.
- Add one new tool page (county-progress.php) and nav card link.
- Keep style consistent with Bootstrap card/table patterns already used.

## Open decisions

1. Primary audience:
   - US counties first, or multi-country admin level 2 first?
2. Find detection rule:
   - Strict (must match uploader username) vs permissive (any Found it log in GPX).
3. Storage:
   - Stateless per upload only, or optional saved snapshots?
