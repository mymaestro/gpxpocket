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
// Returns map of cacheCode => array of matched page metadata (one entry per matched
// page).  A find in an overlapping-edition state (e.g. Arkansas / Arkansas 2, or
// California / NorCal / SoCal) will produce multiple entries — one per edition whose
// polygon contains the find's coordinates.
// Requires county_lookup_helpers.php (countyLookupPointInRing).
if (!function_exists('matchDeLormePagesToFinds')) {
    function matchDeLormePagesToFinds($findsByCode, $indexPages, $dataDir) {
        // Books that share geographic coverage across multiple editions.  A find
        // whose coordinates fall inside the overlap area may legitimately belong to
        // every book in its group, so we do NOT early-exit after the first match.
        $overlapGroups = array(
            array('Arkansas',  'Arkansas 2'),
            array('Florida',   'Florida 2'),
            array('Minnesota', 'Minnesota 2'),
            array('Utah',      'Utah 2'),
            array('Wisconsin', 'Wisconsin 2'),
            array('Wyoming',   'Wyoming 2'),
            array('California', 'NorCal', 'SoCal'),
        );
        $overlapBookNames = array();
        foreach ($overlapGroups as $group) {
            foreach ($group as $n) {
                $overlapBookNames[$n] = true;
            }
        }

        // Group index pages by book once.
        $pagesByBook = array();
        foreach ($indexPages as $page) {
            if (!isset($page['bookName'])) {
                continue;
            }
            $pagesByBook[$page['bookName']][] = $page;
        }

        // cacheCode => [ page_metadata, ... ]
        $results = array();
        $remainingCodes = array_keys($findsByCode);

        // ---- Pass 1: overlap groups ------------------------------------------------
        // Each find is checked against every book in the group; it may accumulate
        // matches in more than one book simultaneously.
        foreach ($overlapGroups as $group) {
            $groupBookPages = array();
            $groupPolygons  = array();
            foreach ($group as $bookName) {
                if (!isset($pagesByBook[$bookName])) {
                    continue;
                }
                $polygons = loadDeLormeBookPolygons($bookName, $dataDir);
                if (count($polygons) < 1) {
                    continue;
                }
                $groupBookPages[$bookName] = $pagesByBook[$bookName];
                $groupPolygons[$bookName]  = $polygons;
            }

            if (count($groupBookPages) < 1) {
                continue;
            }

            foreach ($remainingCodes as $code) {
                if (!isset($findsByCode[$code])) {
                    continue;
                }
                $find = $findsByCode[$code];
                $lat  = (float)$find['lat'];
                $lon  = (float)$find['lon'];

                foreach ($groupBookPages as $bookName => $bookPages) {
                    foreach ($bookPages as $candidate) {
                        if (!isset($candidate['bbox']) || count($candidate['bbox']) < 4 || !isset($candidate['id'])) {
                            continue;
                        }
                        $bbox = $candidate['bbox'];
                        if ($lon < $bbox[0] || $lon > $bbox[2] || $lat < $bbox[1] || $lat > $bbox[3]) {
                            continue;
                        }
                        $pageId = $candidate['id'];
                        if (!isset($groupPolygons[$bookName][$pageId])) {
                            continue;
                        }
                        foreach ($groupPolygons[$bookName][$pageId] as $polygonRing) {
                            if (countyLookupPointInRing($lon, $lat, $polygonRing)) {
                                $results[$code][] = $candidate;
                                break; // stop checking rings for this page
                            }
                        }
                    }
                }
            }

            unset($groupPolygons);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Finds that matched anything in an overlap group cannot also belong to a
        // non-overlapping standalone book, so remove them from further processing.
        $overlapMatchedCodes = array_flip(array_keys($results));
        $remainingCodes = array_values(array_filter(
            $remainingCodes,
            function ($c) use ($overlapMatchedCodes) { return !isset($overlapMatchedCodes[$c]); }
        ));

        // ---- Pass 2: standalone books (first match wins, early-exit kept) ----------
        foreach ($pagesByBook as $bookName => $bookPages) {
            if (isset($overlapBookNames[$bookName])) {
                continue; // already handled in pass 1
            }
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

                $find    = $findsByCode[$code];
                $lat     = (float)$find['lat'];
                $lon     = (float)$find['lon'];
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
                            $results[$code][] = $candidate;
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
