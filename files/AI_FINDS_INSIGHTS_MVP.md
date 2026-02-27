# AI insights for finds history (MVP spec)

## Goal

Turn exported geocaching history into practical, personal insights:

- What you do most
- How your behavior changes over time
- What to do next to hit goals faster

## Scope

MVP is analysis-first (deterministic stats + rules), with optional AI narrative summaries.

Out of scope for MVP:

- External profile scraping
- Real-time geocaching.com API integrations
- Heavy ML training pipelines

## Inputs

Primary input:

- "My Finds" Pocket Query GPX/ZIP exports

Optional inputs:

- Additional historical GPX snapshots for backfill
- User config: home region, preferred distance radius, challenge goals

## Normalized data model

### Find event

Each row represents one confirmed find by the user:

- cache_code
- cache_name
- cache_url
- found_at_utc
- found_day (YYYY-MM-DD)
- cache_type
- container
- difficulty
- terrain
- lat
- lon
- country
- state
- county (if enrichment dataset available)

### Derived fields

Computed at ingest:

- dt_bucket (for grid tracking)
- weekday
- month_key (YYYY-MM)
- distance_from_home_km (if home configured)
- local_vs_trip flag (threshold-based)

## Core feature calculations

### 1) Habit profile

- Type distribution: top cache types by count and share
- D/T profile: median difficulty, median terrain, common DT buckets
- Day-of-week and month seasonality

### 2) Streak analysis

- Longest daily streak of finds
- Current streak
- Streak breaks with nearest restart dates
- Monthly consistency score:
  - active_days_in_month / total_days_in_month

### 3) Geography patterns

- Unique counties/states found per month
- New-area events: first-time county/state finds
- Trip bursts: runs of finds outside local radius

### 4) Challenge forecast (rule-based)

- DT grid completion percent
- Remaining counties in target scope
- Estimated completion date:
  - remaining / rolling_30_day_pace

### 5) DNF / friction proxy (optional for MVP+)

If DNF history is available from uploaded caches:

- DNF-to-find ratio by terrain or type
- "High-friction" contexts to avoid when time-limited

## First 3 cards to implement

### Card A: Your caching profile

Display:

- Top 3 cache types
- Median D/T
- Most active weekday

Data needed:

- find event table only

### Card B: Streaks and consistency

Display:

- Current streak
- Longest streak
- Last 6 months consistency sparkline

Data needed:

- found_day

### Card C: Exploration momentum

Display:

- New counties this month
- New counties in last 90 days
- Local vs trip split (%)

Data needed:

- county enrichment + distance_from_home_km

## AI narrative layer (optional but recommended)

Generate short summaries from computed metrics, for example:

- "You are most active on Saturdays, with 42% of finds in Traditional caches."
- "Your longest streak was 11 days; current trend suggests a possible new streak next month."

Guardrails:

- Only summarize computed facts (no free-form guessing).
- Include confidence labels when data coverage is low.

## UX sketch

Single page: "Finds Insights"

Sections:

1. Upload + profile settings
2. Summary cards (3 MVP cards)
3. Monthly timeline chart
4. Insight bullets (AI narrative)
5. Export metrics CSV

## Technical implementation notes

- Reuse existing GPX upload + ZIP extraction logic.
- Parse groundspeak logs and keep only events matching user identity.
- Use local county GeoJSON enrichment when available.
- Cache normalized rows in-memory per request for MVP.

## User identity matching

Priority order:

1. Explicit username provided in form
2. Auto-detect from dominant finder name in "My Finds" dataset

Rule:

- Keep only logs where log type is "Found it" and finder matches selected identity.

## Data quality checks

- Warn when less than N finds are available (for example N=200).
- Warn when county enrichment coverage is below threshold (for example < 80%).
- Show ingest summary: files, finds accepted, finds skipped.

## Delivery phases

Phase 1:

- Build normalized finds dataset
- Render first 3 cards
- Export metrics CSV

Phase 2:

- Add challenge forecast card
- Add AI narrative summaries

Phase 3:

- Add social overlap insights (friends/co-finders)
- Add map-based exploration playback
