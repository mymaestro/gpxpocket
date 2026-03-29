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

## Phase 2+ enhancements
These modules extend MVP once core parsing and section rendering are stable:

- **Combo Query Analysis**: Integrate features from Combo Pocket Query Insights (multi-query upload, new/disabled/archived/D/T changes, FTF opportunities, no recent activity, finder activity trends).
- **Radius tools**: Home coordinate input, nearest/farthest, and map overlays.
- **Advanced chronology slices**: Breakdowns by cache type and day-of-year overlays.
- **Challenge panels**: Fizzy/Jasmer/birthday/alphanumeric where source data supports it.
- **Owner stats**: Additional owner-centric trends and richer memorable-find rules.
- **Finder Activity Trends**: Visualize streaks, gaps, and changing find patterns.
- **No Recent Activity Finder**: Highlight caches with no logs in X days.
- **First-to-Find Opportunities**: Surface caches with no finds or owner-note-only activity.
- **D/T Change Tracker**: Show caches where D/T ratings changed over time.

## Challenge helper catalog (future modules)
Challenge Checkers already exist at Project-GC. This project can add **Challenge Helpers** focused on progress tracking, opportunity finders, and visualizations.

- **JASMER**: Find a cache published in every month since May 2000.
- **Fizzy (D/T Grid)**: Complete all 81 D/T combinations; support loop tracking.
- **Oldies**: Find the X oldest active geocaches in a region.
- **BADGES**: Track all-round achievement tiers (similar to Project-GC/GSAK).
- **Streaking**: Find every day for a target duration (30, 183, 366, etc.).
- **Calendars**: Find on every day-of-year and/or cache placed on each day-of-year.
- **DT Average**: Raise average Difficulty/Terrain above a threshold.
- **Milestones**: Track meaningful count intervals (10s, 100s, 1000s, 10000s, etc.).
- **Unloved**: Count finds with long no-find periods (183/365+ days).
- **Mayoralty**: Complete all caches in a defined area.
- **Radius**: Complete all caches within a configured distance from home.
- **Degrees**: Find at all 360 bearings from home coordinates.

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


---

### Action plan from March 28

Yes, this is exactly the right time for a feature branch, and I created it:

feature/persist-my-finds-workflow

Your idea is strong and would be a big UX improvement: upload My Finds once, then reuse it across all helper pages.

Recommended direction:

1. Introduce a user profile dataset on server
  - Store parsed My Finds once per user profile.
  - Keep normalized fields you already use: cache code, found date, lat/lon, name, URL.

2. Add a shared data layer
  - One include helper to load/save profile finds and return the in-memory map all pages use.
  - Existing progress/challenge pages call this instead of requiring new upload every time.

3. Keep upload optional per page at first
  - Phase 1: if profile exists, use it; if not, allow upload inline.
  - Phase 2: move to a dedicated “My Finds Profile” upload/manage page.

4. Add freshness and re-upload flow
  - Show when profile was last updated.
  - Add “replace profile data” and possibly merge mode.

5. Privacy/safety guardrails
  - Store by username + opaque profile ID or session token.
  - Add retention policy (for example 30-90 days), size limits, and delete/reset action.
  - Avoid exposing raw uploads publicly.

Suggested implementation order:

1. Build profile storage helper and a tiny profile upload endpoint.
2. Wire one page (for example DeLorme) to consume persisted finds.
3. Reuse that same helper in county/challenge helpers.
4. Add profile management UI and retention cleanup.

More about this **Opportunity Seeker** page:

For this new Opportunity Seeker page, I’d use an anonymous capability-based profile model, not accounts.

Recommended model

1. User uploads My Finds once on Opportunity Seeker.
2. Server creates an anonymous profile ID plus a high-entropy secret token.
3. Token is stored in an HttpOnly, Secure, SameSite cookie.
4. Parsed finds are stored server-side under that profile ID.
5. Future Opportunity Seeker runs reuse stored finds automatically.
6. Challenge/trip queries are uploaded per run (or cached separately later).
7. This gives persistence without login, and access control is “whoever has the secret token cookie”.

Why this matches your goals

1. No new user identity system.
2. Existing progress pages remain untouched.
3. Very low chance of cross-user access if token is random (at least 128 bits, preferably 256 bits).
4. Easy UX: upload once, reuse many times.

Important security reality
You can prevent users from seeing each other’s stored finds in your app, but you cannot cryptographically prove file ownership without geocaching.com OAuth/session integration.
If someone shares their My Finds GPX file externally, your app cannot distinguish that from a legitimate upload. So the right goal is isolation and confidentiality in your system, not provenance proof.

Validation rules I’d add

1. Persisted baseline dataset must be explicitly marked “My Finds import”.
2. Enforce “single finder identity” in found logs for that import.
3. Show detected finder username/id before saving and require confirmation.
4. Reject imports with many distinct finders for the baseline profile dataset.

Data lifecycle controls

1. “Reset my profile” button (deletes server copy and rotates token).
2. Optional recovery key export for moving devices.
3. TTL cleanup (for example 60-90 days inactivity).
4. Encryption at rest for stored parsed finds.

---
## Opportunity seeking - ChallengeFiller

The user uploads all of their finds and a pocket query of all caches in a geographic region.
The page then shows which PQ caches help them meet challenges like missing counties, DeLorme pages, or D/T tracker gaps.

This is the killer feature — it flips the tool from "look what I've done" to "here's exactly what to go find tomorrow."
The core logic is essentially a diff engine:

Left side: what the user has (their finds, typed and located, summarized as a profile/dashboard)
Right side: what's available (the PQ caches, typed and located)
Filter: which available caches fill a challenge gap

The output for each cache in the PQ would be something like:

GC12345 - Some Cache Name
Traditional | D2/T1.5
📍 Bexar County ← missing 2-step Traditional ✅
📄 DeLorme TX Page 53 ← incomplete ✅
🎯 Fills 2 challenges

So a single cache gets scored by how many challenges it advances, and results are sorted by that score to show the highest-value targets first.
D/T tracker is interesting too — that's a 10x10 grid of Difficulty/Terrain combinations, every 0.5 step. You'd need to track which cells the user is missing and flag PQ caches that fill empty cells.

### Current implementation status (March 28)

ChallengeFiller now has a working MVP in this branch.

Implemented:

1. New page scaffold and workflow:
  - gpxchallengefiller.php
  - Upload My Finds (optional per run) + upload regional PQ files (required per scoring run)

2. Anonymous persisted profile model:
  - includes/profile_token_helpers.php
  - includes/profile_storage_helpers.php
  - Cookie-based token (HttpOnly + SameSite), deterministic profile id, server-side stored My Finds baseline
  - Reset profile support (delete stored baseline + rotate token)

3. Challenge scoring engine:
  - includes/challengefiller_helpers.php
  - Parse regional PQ caches and exclude already found caches
  - Ignore unavailable/archived candidates
  - Score opportunities by number of challenge signals hit

4. Supported challenge signals in MVP:
  - Missing county (using county polygon lookup)
  - Missing DeLorme page (using DeLorme polygon matching)
  - Missing D/T cell (0.5-step matrix from 0.5 to 5.0)

5. UI output in MVP:
  - Ranked opportunity table sorted by score (high to low)
  - CSV export of ranked opportunities
  - Coverage summary cards for baseline and opportunities

6. Site wiring:
  - Nav item added in includes/layout.php
  - Home-page card added in index.php

Known gaps (next iteration):

1. Signal display is table-centric, not yet the richer cache card format in the vision example.
2. No interactive D/T grid click-through yet.
3. No map view yet for opportunities.
4. Project-GC parity checks are pending (covered in test plan below).

---
## ChallengeFiller test plan

Here’s the fastest real-data validation pass for ChallengeFiller:

1. Baseline persistence test
  - Open `gpxchallengefiller.php`.
  - Upload one real My Finds PQ + username.
  - Confirm success message and baseline count.
  - Refresh the page and run again with only a regional PQ upload.
  - Expected: it still works (proves stored profile reuse).

2. Core opportunity correctness test
  - Use a regional PQ where you already know a few candidate caches.
  - Export CSV from the results table.
  - Spot-check 15-25 rows manually:
  - GC code is not already in My Finds.
  - Cache is available and not archived.
  - County signal is truly missing from your county coverage.
  - DeLorme signal is truly missing from your DeLorme coverage.
  - D/T signal is truly one of your missing cells.

3. Challenge-specific sanity slices
  - Filter/export top rows with score >= 2.
  - Verify these are genuinely “high-value” targets (multi-challenge fillers).
  - Check some low-score rows to ensure signal counts are consistent.

4. Persistence/reset safety test
  - Use Reset Stored Profile.
  - Re-run with only regional PQ: should warn no baseline.
  - Re-upload My Finds and verify new baseline is active.

Then do a Project-GC comparison pass (same account, same challenge type, same region/time window):

1. Compare missing D/T cells from ChallengeFiller vs Project-GC.
2. Compare missing counties list.
3. Compare DeLorme page gaps.
4. Note any mismatches with exact cache examples and we’ll tune parsing/rules.
