<?php

// Load the bbox-only index from delorme-pages.json.
// Polygons are NOT included; use matchDeLormePagesToFinds() for full matching.
if (!function_exists('loadDeLormePages')) {
    function loadDeLormePages($indexPath, &$errorMessage) {
        if (!file_exists($indexPath)) {
            $errorMessage = 'DeLorme page index not found at ' . $indexPath . '.';
            return array();
        }

        $raw = @file_get_contents($indexPath);
        if ($raw === false || trim($raw) === '') {
            $errorMessage = 'Unable to read DeLorme page index.';
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['pages']) || !is_array($decoded['pages'])) {
            $errorMessage = 'DeLorme page index is not valid (missing pages array).';
            return array();
        }

        if (count($decoded['pages']) < 1) {
            $errorMessage = 'DeLorme page index contains no pages.';
            return array();
        }

        return $decoded['pages'];
    }
}

// Return the filesystem slug for a book name, matching the filenames under data/delorme/.
if (!function_exists('deLormeBookSlug')) {
    function deLormeBookSlug($bookName) {
        return strtolower(preg_replace('/\s+/', '-', trim((string)$bookName)));
    }
}

// Load polygon data for one book from $dataDir.
// Returns array keyed by page id => list of polygon rings (arrays of [lon, lat] pairs).
if (!function_exists('loadDeLormeBookPolygons')) {
    function loadDeLormeBookPolygons($bookName, $dataDir) {
        $slug = deLormeBookSlug($bookName);
        $path = rtrim($dataDir, '/') . '/' . $slug . '.json';
        if (!file_exists($path)) {
            return array();
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['pages'])) {
            return array();
        }

        $indexed = array();
        foreach ($decoded['pages'] as $p) {
            if (isset($p['id'], $p['polygon'])) {
                if (!isset($indexed[$p['id']])) {
                    $indexed[$p['id']] = array();
                }
                $indexed[$p['id']][] = $p['polygon'];
            }
        }

        return $indexed;
    }
}

// Batch-match an array of finds to DeLorme pages.
//
// $findsByCode  — map of cacheCode => ['lat'=>..., 'lon'=>...] (and other fields)
// $indexPages   — result of loadDeLormePages() (bbox metadata only, no polygons)
// $dataDir      — path to the directory containing per-book polygon JSON files
//
// Returns map of cacheCode => matched page metadata array.
// Requires county_lookup_helpers.php (countyLookupPointInRing).
if (!function_exists('matchDeLormePagesToFinds')) {
    function matchDeLormePagesToFinds($findsByCode, $indexPages, $dataDir) {
        // Group index pages by book once, then process one book at a time.
        // This avoids keeping large candidate maps and many decoded polygon files in memory.
        $pagesByBook = array();
        foreach ($indexPages as $page) {
            if (!isset($page['bookName'])) {
                continue;
            }
            $pagesByBook[$page['bookName']][] = $page;
        }

        $results = array();
        $remainingCodes = array_keys($findsByCode);

        foreach ($pagesByBook as $bookName => $bookPages) {
            if (count($remainingCodes) < 1) {
                break;
            }

            $polygons = loadDeLormeBookPolygons($bookName, $dataDir);
            if (count($polygons) < 1) {
                continue;
            }

            $nextRemaining = array();

            foreach ($remainingCodes as $code) {
                if (!isset($findsByCode[$code])) {
                    continue;
                }

                $find = $findsByCode[$code];
                $lat = (float)$find['lat'];
                $lon = (float)$find['lon'];
                $matched = false;

                foreach ($bookPages as $candidate) {
                    if (!isset($candidate['bbox']) || count($candidate['bbox']) < 4 || !isset($candidate['id'])) {
                        continue;
                    }

                    $bbox = $candidate['bbox'];
                    if ($lon < $bbox[0] || $lon > $bbox[2] || $lat < $bbox[1] || $lat > $bbox[3]) {
                        continue;
                    }

                    $pageId = $candidate['id'];
                    if (!isset($polygons[$pageId]) || !is_array($polygons[$pageId])) {
                        continue;
                    }

                    foreach ($polygons[$pageId] as $polygonRing) {
                        if (countyLookupPointInRing($lon, $lat, $polygonRing)) {
                            $results[$code] = $candidate;
                            $matched = true;
                            break 2;
                        }
                    }
                }

                if (!$matched) {
                    $nextRemaining[] = $code;
                }
            }

            $remainingCodes = $nextRemaining;

            // Release per-book polygon memory before loading the next book.
            unset($polygons);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return $results;
    }
}
