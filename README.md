# gpxpocket
Tools to help you report from Geocaching Pocket Queries

## Planned utilities

- Watchlist Alerts (new, disabled, archived, and D/T changes)
- No-Recent-Activity Finder (no logs in X days)
- FTF Opportunity Scan (recent publishes with no found logs)
- Route Optimizer (ordered cache list from a start point)
- County/Region Progress Tracker (coverage and missing areas)
- Challenge Checker (DT grid, year spread, and combo checks)
- Geocaching Friends Finder (identify recurring finders across your Pocket Queries)
- Finder Activity Explorer (lists, timelines, and maps of where selected cachers have been)

Implementation notes for AI finds insights: [files/AI_FINDS_INSIGHTS_MVP.md](files/AI_FINDS_INSIGHTS_MVP.md).

Implementation notes for caching friends feature: [files/CACHING_FRIENDS_FEATURE_PLAN.md](files/CACHING_FRIENDS_FEATURE_PLAN.md).

Implementation notes for county/region progress: [files/COUNTY_REGION_PROGRESS_PLAN.md](files/COUNTY_REGION_PROGRESS_PLAN.md).

## Pocket Query GPX field map

Reference sample: Groundspeak Pocket Query GPX (`creator="Groundspeak Pocket Query"`) with Topografix GPX 1.0 root namespace and Groundspeak cache namespace.

| Purpose | XPath / location | Example | Notes |
|---|---|---|---|
| Snapshot name | `/gpx/name` | `7 miles around NW Austin` | Pocket Query display name |
| Snapshot timestamp | `/gpx/time` | `2026-02-22T08:42:09.2425945Z` | Use as baseline snapshot date |
| Bounds | `/gpx/bounds/@minlat ...` | `minlat=... maxlon=...` | Optional map extent |
| Cache list | `/gpx/wpt` | many rows | One waypoint per cache |
| Cache code | `/gpx/wpt/name` | `GC4HBG0` | Stable cache key for diffs |
| Coordinates | `/gpx/wpt/@lat`, `/gpx/wpt/@lon` | `30.461833`, `-97.750333` | Decimal degrees |
| Cache URL | `/gpx/wpt/url` | `https://coord.info/GC4HBG0` | Direct cache link |
| Cache title | `/gpx/wpt/urlname` | `Sunburned Zebra V` | Human-readable name |
| GPX type marker | `/gpx/wpt/type` | `Geocache|Traditional Cache` | Split on `|` for subtype |
| Availability flags | `groundspeak:cache/@available`, `@archived` | `True`, `False` | Good for watchlist alerts |
| Container | `groundspeak:cache/groundspeak:container` | `Micro` | Size/category field |
| Difficulty / terrain | `groundspeak:cache/groundspeak:difficulty`, `/terrain` | `3.0`, `1.5` | Core ranking fields |
| Country / state | `groundspeak:cache/groundspeak:country`, `/state` | `United States`, `Texas` | Region aggregation inputs |
| Attributes | `groundspeak:cache/groundspeak:attributes/groundspeak:attribute` | `id="13" inc="1"` | Feature flags and exclusions |
| Descriptions | `groundspeak:cache/groundspeak:short_description`, `/long_description` | HTML-encoded text | May contain large content |
| Hint | `groundspeak:cache/groundspeak:encoded_hints` | `white` | ROT13 in many exports |
| Logs list | `groundspeak:cache/groundspeak:logs/groundspeak:log` | repeating | Usually newest first |
| Log date/type/finder/text | `groundspeak:log/*` | `Found it`, username, message | Inputs for activity checks |

### Parser notes

- Root GPX nodes are in `http://www.topografix.com/GPX/1/0`.
- Cache details are in `http://www.groundspeak.com/cache/1/0/1`.
- Prefer cache code (`GC...`) as primary key across snapshots.
- For log diffs, compare a stable signature such as `sha1(date|type|finder|text)`.
- ZIP Pocket Query bundles may include `*-wpts.gpx`; skip those for cache-table tools.

Checklist for implementation consistency: [files/PARSER_CHECKLIST.md](files/PARSER_CHECKLIST.md).
