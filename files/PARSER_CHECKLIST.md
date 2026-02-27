# Pocket Query GPX parser checklist

Use this checklist when adding or modifying GPX parsing in any tool.

## 1) Input and safety

- Accept `.gpx` and `.zip` uploads only.
- Enforce file size limits and reject empty files.
- Verify upload source with `is_uploaded_file` before processing.
- For ZIP uploads, extract only top-level `.gpx` cache files.
- Ignore waypoint companion files like `*-wpts.gpx`.
- Parse XML with `LIBXML_NONET`.

## 2) Namespace handling

- GPX root namespace: `http://www.topografix.com/GPX/1/0`.
- Groundspeak namespace: `http://www.groundspeak.com/cache/1/0/1`.
- Read waypoint fields from GPX nodes and details/logs from Groundspeak nodes.

## 3) Required snapshot fields

- Snapshot name: `/gpx/name`.
- Snapshot time: `/gpx/time` (fallback to file mtime if missing/invalid).
- Cache rows: `/gpx/wpt`.

## 4) Required cache fields

- Cache key: `/gpx/wpt/name` (GC code).
- Coordinates: `/gpx/wpt/@lat`, `/gpx/wpt/@lon`.
- URL and title: `/gpx/wpt/url`, `/gpx/wpt/urlname`.
- Type marker: `/gpx/wpt/type` (split on `|` where needed).
- Status flags: `groundspeak:cache/@available`, `@archived`.
- Core metadata: container, difficulty, terrain, country, state.

## 5) Log parsing and diffing

- Parse all logs from `groundspeak:cache/groundspeak:logs/groundspeak:log`.
- Capture log date, type, finder, text.
- Normalize whitespace in log text before comparisons.
- Use a stable log signature for diffs: `sha1(date|type|finder|text)`.

## 6) Normalization conventions

- Treat cache code as the canonical identity across snapshots.
- Sort cache collections by cache code for stable output.
- Parse dates with fallback behavior when timestamps are malformed.
- Keep output labels consistent across tools (status/date/type naming).

## 7) Error handling

- Return user-facing errors for invalid XML, unsupported file types, and ZIPs without GPX.
- Continue processing multi-file uploads when possible and report partial failures.
- Clean up temporary extracted files after parsing.

## 8) Regression checks before release

- Verify with at least one real Pocket Query GPX sample.
- Verify ZIP handling with both cache GPX and `wpts` GPX present.
- Verify log diff results stay stable between repeated runs.
- Verify missing optional fields do not break rendering/export.
