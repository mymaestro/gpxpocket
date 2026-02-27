# Caching Friends feature plan (MVP)

## Goal

Identify recurring cachers in uploaded Pocket Query data and provide actionable views:

- Who appears most often in your cache logs
- Where they cache
- When overlap with your activity is highest

## Primary signal

From each cache log entry:

- `groundspeak:finder` text (display name)
- `groundspeak:finder @id` (stable numeric user id)

Use finder id as primary key when available.

## Inputs

Required:

- One or more GPX/ZIP files (preferably "My Finds" + nearby-area PQ exports)

Optional:

- Selected user identity (to exclude self)
- Date range filter

## Normalized friend event model

Each parsed log contributes an event row:

- cache_code
- cache_name
- cache_url
- cache_lat
- cache_lon
- log_id
- log_date
- log_type
- finder_id
- finder_name
- snapshot_time

Derived fields:

- event_day (YYYY-MM-DD)
- event_month (YYYY-MM)
- county/state (if enrichment enabled)

## Identity and dedup rules

1. Dedup by `log_id` globally across uploaded files.
2. If `log_id` missing, skip row for MVP (keep deterministic behavior).
3. Exclude selected self user from friend ranking.
4. Collapse alias drift by finder id first, then finder name fallback.

## Friend scoring (MVP)

For each finder:

- `log_count`: total log entries seen
- `unique_cache_count`: distinct caches logged
- `recent_activity_90d`: logs in last 90 days
- `co_presence_count`: caches where both user and finder appear in logs

Composite score example:

- `score = unique_cache_count * 2 + recent_activity_90d + co_presence_count`

Sort descending by score.

## MVP outputs

### 1) Friends leaderboard

Columns:

- Finder
- Logs
- Unique caches
- Last seen
- Co-presence count

### 2) Friend detail panel

For selected friend:

- Activity timeline by month
- Top cache types logged
- Top counties/states (if enrichment available)

### 3) Shared places list

- Caches where both you and selected friend have logged
- Include dates and links

## Optional map/timeline outputs (MVP+)

- Friend activity map (cluster markers)
- Monthly animation/playback

## Privacy and controls

- Process data locally; do not persist by default.
- Provide "Exclude my own logs" toggle.
- Allow hidden mode for selected finder names in UI export.

## Technical fit

- Reuse upload parsing from existing pages.
- Reuse log dedup pattern (`log id`) from `gpxhistory.php`.
- Reuse county enrichment path from county-progress work.

## Phase plan

Phase 1 (MVP):

- Parse finder id/name + log id/date/type
- Build leaderboard and friend detail table
- Export leaderboard CSV

Phase 2:

- Add co-presence scoring and shared places table
- Add date range filter and cache-type filter

Phase 3:

- Add timeline and map visualizations
- Add AI-generated monthly friend insights summary

## Open decisions

1. Default self-detection:
   - Explicit username input vs auto-detect from dominant finder id
2. Ranking formula:
   - Simple counts vs weighted recency + co-presence
3. Visibility:
   - Full names in exports vs optional anonymization
