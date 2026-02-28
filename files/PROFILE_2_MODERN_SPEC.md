# Profile 2.0 Modernization Spec

## Goal

Create a modern, modular replacement for classic geocaching profile-stat builders with:

- clearer navigation
- interactive charts/maps
- faster incremental rendering
- phased delivery (MVP first)

This spec targets a new tool page in this project (PHP-rendered + JS charts/maps), not a full platform rewrite.

---

## Product vision

A single "Profile 2.0" page where a cacher can upload find-history exports and generate:

- chronology + activity trends
- cache/container/type distribution
- D/T, challenge, and milestone progress
- radius + memorable-find highlights

The UI should feel modern and scannable: section cards, sticky section nav, responsive layouts, and visual summaries first (tables second).

---

## Input model

### Required (MVP)

- One or more GPX/ZIP files containing finds/logs with dates and cache metadata.

### Optional (phase 2+)

- Additional exports for placed-date challenges.
- User settings (timezone/date format/home coordinates/exclusions).
- Adventure Lab count/manual entry.

### Data constraints

- Some challenge stats require full historical coverage (not just recent PQ windows).
- Must clearly label confidence/coverage if data appears partial.

---

## Information architecture

## Section navigation

- Sticky in-page nav (desktop) + compact jump menu (mobile).
- Section-level status chips (e.g., "ready", "partial data", "missing input").

## Primary sections

1. Overview
2. Chronology
3. Cache & Container Types
4. Difficulty/Terrain
5. Radius & Geography
6. Milestones & Memorable Finds
7. Challenges
8. Owners/Badges/Misc
9. Settings & Data Notes

---

## MVP scope (phase 1)

Deliver quickly with high value:

1. **Overview**
   - total finds
   - active years
   - most recent find
   - top cache types

2. **Chronology**
   - finds per month (bar)
   - cumulative finds (line)
   - day-of-week distribution

3. **Types + Containers**
   - cache type distribution
   - container type distribution

4. **D/T Matrix**
   - 9x9 (or normalized) heatmap of found combinations

5. **Memorable Finds (basic)**
   - oldest cache found
   - highest D/T finds
   - simple milestone intervals (100/500/1000 etc.)

6. **Cluster/Friends integration hook**
   - link out to `gpxfriends.php` for social analysis

---

## Phase 2 scope

- Radius tools with home coordinate input + nearest/farthest + map.
- Advanced chronology slices (by cache type, day-of-year overlays).
- Challenge panels (Fizzy/Jasmer/birthday/alphanumeric) where source data allows.
- Owner stats and richer memorable-find rules.

---

## Phase 3 scope

- Power-user challenge customization.
- Badge generation system.
- Performance optimizations for very large histories.
- Export/report bundles (print/PDF/shareable snapshot).

---

## UX/UI requirements

- Responsive card-based layout; avoid dense full-width tables by default.
- Charts first, details expandable (accordion/disclosure).
- Consistent metric badges and section headers.
- Empty/loading/error states for each section.
- Accessibility:
  - semantic headings
  - keyboard-friendly controls
  - chart alternatives (summary text/table)

---

## Technical approach

- Keep PHP as orchestration layer and server-side parser.
- Use shared helper includes (`includes/gpx_helpers.php`, `includes/gpx_format_helpers.php`).
- Front-end visualization candidates:
  - Chart.js for trend/distribution charts
  - Leaflet for map/radius panels
- Normalize parsed events to a single internal event shape early.

---

## Performance notes

- Parse once per upload and cache derived aggregates in-memory per request.
- Avoid repeated nested scans for each section; compute reusable summaries.
- For large datasets, progressively render heavy sections after overview.

---

## Validation checklist

- Handles GPX + ZIP + mixed uploads safely.
- Correctly deduplicates logs (by stable log id).
- Correct timezone/date presentation according to settings.
- Graceful behavior for partial datasets.
- Clear labels where a metric depends on unavailable fields.

---

## Proposed implementation sequence

1. Scaffold `gpxprofile.php` page shell + section nav.
2. Build parser/normalizer for profile aggregates.
3. Implement MVP overview + chronology + types + D/T.
4. Add milestone panel and section-level data quality notes.
5. Add radius/challenge modules incrementally.
6. Polish UX, tune performance, add export options.

---

## Out of scope (for now)

- Full clone of every historical mygeocachingprofile control.
- User account system or persistent cloud storage.
- Complex social/sharing features beyond local report generation.
