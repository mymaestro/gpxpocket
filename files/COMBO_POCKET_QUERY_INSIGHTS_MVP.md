# Combo Pocket Query Insights MVP Spec

## Purpose
A single page to analyze and surface insights from one or more uploaded Pocket Queries (GPX files), helping users discover new opportunities and trends.

## Core Features

1. **Multi-Query Upload/Selection**
   - Allow user to upload/select one or more Pocket Query GPX files.
   - Parse and merge data for analysis.

2. **Filters & Buttons**
   - Filter by: New, Disabled, Archived caches.
   - Highlight caches with Difficulty/Terrain (D/T) rating changes.

3. **First-to-Find (FTF) Opportunities**
   - Identify caches with no found logs or only owner notes.
   - Option to filter for recently published caches.

4. **No Recent Activity Finder**
   - List caches with no logs in the last X days (user configurable).

5. **Finder Activity Trends**
   - Select a finder (by username) and display their recent logs across all queries.
   - Show trends: frequency, types of finds, D/T spread, etc.

## UI Elements

- **File Upload/Selection**: Drag-and-drop or file picker for GPX files.
- **Filter Controls**: Checkboxes/toggles for New, Disabled, Archived, D/T changes, FTF, No Recent Activity.
- **Finder Search**: Input to search for a specific finder and view their activity.
- **Results Table/List**: Display filtered caches with sortable columns (name, status, D/T, last log, etc.).
- **Export/Download**: Option to export filtered results as CSV.

## MVP Constraints

- Focus on core logic and basic UI; advanced visualizations and map integration are out of scope for MVP.
- No user authentication required.
- Minimal styling; prioritize functionality.
