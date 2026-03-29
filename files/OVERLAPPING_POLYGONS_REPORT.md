# DeLorme Atlas Overlapping Polygon Report

## Affected States: 7 total

There are **6 states with explicit `-2` duplicate atlas edition files**, plus **California split into 3 editions** (`california`, `norcal`, `socal`).

---

## States with `-2` Edition Files

All six have nearly complete geographic overlap — the two editions tile the same state with differently-sized/shaped map pages.

| State | File | bookName | Pages |
|-------|------|----------|-------|
| **Arkansas** | `arkansas.json` | `Arkansas` | 46 |
| | `arkansas-2.json` | `Arkansas 2` | 111 |
| **Florida** | `florida.json` | `Florida` | 131 |
| | `florida-2.json` | `Florida 2` | 149 |
| **Minnesota** | `minnesota.json` | `Minnesota` | 84 |
| | `minnesota-2.json` | `Minnesota 2` | 72 |
| **Utah** | `utah.json` | `Utah` | 48 |
| | `utah-2.json` | `Utah 2` | 53 |
| **Wisconsin** | `wisconsin.json` | `Wisconsin` | 95 |
| | `wisconsin-2.json` | `Wisconsin 2` | 78 |
| **Wyoming** | `wyoming.json` | `Wyoming` | 60 |
| | `wyoming-2.json` | `Wyoming 2` | 60 |

**What differentiates them:** Only the `bookName` prefix in the `id` field (e.g. `"Arkansas|Pg 022"` vs `"Arkansas 2|Pg 016"`). The page grids are from different atlas editions — different scale/page layout — so the polygon shapes and page numbers don't align, but they cover the same geographic area.

### Geographic Overlap Counts

Pages from edition 1 that bbox-intersect any page in edition 2:

| State | Edition 1 overlap | Edition 2 overlap |
|-------|-------------------|-------------------|
| Arkansas | 45/46 | 108/111 |
| Florida | 109/131 | 145/149 |
| Minnesota | 84/84 | 72/72 |
| Utah | 48/48 | 53/53 |
| Wisconsin | 85/95 | 77/78 |
| Wyoming | 60/60 | 60/60 |

### Sample Side-by-Side (Arkansas)

```
Edition 1: id='Arkansas|Pg 022',   page='Pg 022', bbox=(-94.618, 35.983, -94.033, 36.500), pts=117
Edition 2: id='Arkansas 2|Pg 016', page='Pg 016', bbox=(-94.250, 36.133, -93.900, 36.499), pts=142
```

---

## California Split into 3 Editions

| File | bookName | Pages | Lat/Lon Coverage |
|------|----------|-------|-----------------|
| `california.json` | `California` | 144 | Full state: 32.53–42.01°N |
| `norcal.json` | `NorCal` | 104 | North half: 37–42°N |
| `socal.json` | `SoCal` | 115 | South half: 32.53–37°N |

- `NorCal` 104/104 pages overlap geographically with `California`
- `SoCal` 115/115 pages overlap geographically with `California`
- `NorCal` and `SoCal` overlap each other in 12 pages (around the 37°N boundary)

### Sample Side-by-Side

```
California: id='California|Pg 022', page='Pg 022', bbox=(-124.441, 41.367, -123.850, 41.999)
NorCal:     id='NorCal|Pg 022',     page='Pg 022', bbox=(-124.441, 41.500, -123.938, 41.999)
SoCal:      id='SoCal|Pg 018',      page='Pg 018', bbox=(-122.266, 36.500, -121.813, 37.000)
```

---

## Summary of Property Differences

The **only distinguishing property** across all duplicate sets is the `id`/`bookName` prefix. There are no edition year, scale, or other metadata fields — just `id`, `page`, and `polygon` on each feature. The polygons themselves are geometrically different (different atlas grid layouts), but cover the same territory.
