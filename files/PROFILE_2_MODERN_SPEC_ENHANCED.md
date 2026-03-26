# Profile 2.0 Modernization Spec (Enhanced)

## Goal
Create a modern, modular replacement for classic geocaching profile-stat builders with:
- clearer navigation
- interactive charts/maps
- faster incremental rendering
- phased delivery (MVP first)
- deeper insights and new analysis tools (see Enhancements)

This spec targets a new tool page in this project (PHP-rendered + JS charts/maps), not a full platform rewrite.

---

## Product vision
A single "Profile 2.0" page where a cacher can upload find-history exports and generate:
- chronology + activity trends
- cache/container/type distribution
- D/T, challenge, and milestone progress
- radius + memorable-find highlights
- advanced insights (see below)

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
### Section navigation
- Sticky in-page nav (desktop) + compact jump menu (mobile).
- Section-level status chips (e.g., "ready", "partial data", "missing input").

### Primary sections
1. Overview
2. Chronology
3. Cache & Container Types
4. Difficulty/Terrain
5. Radius & Geography
6. Milestones & Memorable Finds
7. Challenges
8. Owners/Badges/Misc
9. Settings & Data Notes
10. **Advanced Insights** (new)

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



## Additional Challenge Types & Helpers

There are many popular challenge types in geocaching. While Challenge Checkers are already available at project-gc.com, we could add Challenge Helpers to this tool in the future to help users track progress and find opportunities for these challenges:

- **JASMER**: Find a cache published in every month since May 2000.
- **Fizzy (or DT Grid)**: Find a cache with every combination of D and T rating (81 combos). 'Looping' means completing the grid multiple times.
- **Oldies**: Find the X oldest active geocaches in a region (country, state, county, etc.).
- **BADGES**: Earn badges for all-round achievement (as in Project-GC and GSAK), including gemstone and black belt levels.
- **Streaking**: Find a cache every day for a set period (e.g., 30, 183, or 366 days).
- **Calendars**: Find a cache on every date of the year (any year), and/or find a cache published on each date of the year.
- **DT Average**: Raise your average Difficulty and Terrain ratings above a threshold (e.g., 1.8, 1.9, 2.0).
- **Milestones**: Celebrate every major find count (10s, 100s, 1000s, 10000s, etc.), making each milestone memorable.
- **Unloved**: Find caches that haven't been found for at least 183 or 365 days; sum the 'days of unloved' for a cumulative total.
- **Mayoralty**: Find every cache in a defined area (town, county, city region, etc.).
- **Radius**: Find every cache within a certain distance (e.g., 5km, 5 miles, 10km) from your home coordinates.
- **Degrees**: Find a cache on every degree bearing (all 360) from your home coordinates.

These could be surfaced as Challenge Helper modules, providing progress tracking, opportunity finders, and visualizations for each challenge type.
- **Combo Query Analysis**: Integrate features from Combo Pocket Query Insights (multi-query upload, new/disabled/archived/D/T changes, FTF opportunities, no recent activity, finder activity trends)
- **Radius tools** with home coordinate input + nearest/farthest + map.
- **Advanced chronology slices** (by cache type, day-of-year overlays).
- **Challenge panels** (Fizzy/Jasmer/birthday/alphanumeric) where source data allows.
- **Owner stats** and richer memorable-find rules.
- **Finder Activity Trends**: Track and visualize a user's find patterns, streaks, and gaps.
- **No Recent Activity Finder**: Highlight caches with no logs in X days.
- **First-to-Find Opportunities**: Identify caches with no finds or only owner notes.
- **D/T Change Tracker**: Show caches where D/T ratings have changed over time.

### D/T Challenge Grid & Opportunity Finder

**Purpose:**
Help cachers complete their Difficulty/Terrain (D/T) grid by identifying which D/T combinations they have not yet found, and surfacing available caches in a target region that can fill those gaps.

**How it works:**
1. **User Uploads:**
   - Upload "My Finds" Pocket Query (PQ) to show completed D/T combos.
   - Upload one or more region/location PQs to show available caches in the area.

2. **Grid Visualization:**
   - Display a 9x9 D/T grid.
   - Mark cells as “completed” if the cacher has found at least one cache with that D/T combo.
   - Highlight “missing” cells (not yet found).

3. **Opportunity Finder:**
   - For each missing D/T cell, list caches from the region PQ(s) that match the missing combo and are available to find (not found, not archived/disabled).
   - Show cache details (name, GC code, distance, type, etc.).

4. **User Experience:**
   - Interactive grid: clicking a missing cell shows matching caches.
   - Option to export the list of opportunities as CSV.

5. **Advanced (future):**
   - Filter by cache type, size, or other attributes.
   - Map view of missing D/T opportunities.

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
6. Add advanced insights (combo query, FTF, trends, etc).
7. Polish UX, tune performance, add export options.

---

## Out of scope (for now)
- Full clone of every historical mygeocachingprofile control.
- User account system or persistent cloud storage.
- Complex social/sharing features beyond local report generation.
