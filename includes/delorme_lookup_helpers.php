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

// Load polygon data for one book from $dataDir, cached for the lifetime of the request.
// Returns array keyed by page id => polygon (array of [lon, lat] pairs).
if (!function_exists('loadDeLormeBookPolygons')) {
    function loadDeLormeBookPolygons($bookName, $dataDir) {
        static $cache = array();
        if (array_key_exists($bookName, $cache)) {
            return $cache[$bookName];
        }

        $slug = deLormeBookSlug($bookName);
        $path = rtrim($dataDir, '/') . '/' . $slug . '.json';
        if (!file_exists($path)) {
            $cache[$bookName] = array();
            return array();
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            $cache[$bookName] = array();
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['pages'])) {
            $cache[$bookName] = array();
            return array();
        }

        $indexed = array();
        foreach ($decoded['pages'] as $p) {
            if (isset($p['id'], $p['polygon'])) {
                $indexed[$p['id']] = $p['polygon'];
            }
        }

        $cache[$bookName] = $indexed;
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
        // Phase 1: bbox prefilter — gather per-find candidates and note which books are needed.
        $candidatesByCode = array();
        $neededBooks = array();

        foreach ($findsByCode as $code => $find) {
            $lat = (float)$find['lat'];
            $lon = (float)$find['lon'];

            foreach ($indexPages as $page) {
                if (!isset($page['bbox']) || count($page['bbox']) < 4) {
                    continue;
                }
                $bbox = $page['bbox'];
                if ($lon < $bbox[0] || $lon > $bbox[2] || $lat < $bbox[1] || $lat > $bbox[3]) {
                    continue;
                }
                $candidatesByCode[$code][] = $page;
                $neededBooks[$page['bookName']] = true;
            }
        }

        // Phase 2: load polygon data only for the books that have candidates.
        foreach (array_keys($neededBooks) as $bookName) {
            loadDeLormeBookPolygons($bookName, $dataDir);
        }

        // Phase 3: point-in-polygon test on the candidates.
        $results = array();
        foreach ($findsByCode as $code => $find) {
            if (!isset($candidatesByCode[$code])) {
                continue;
            }
            $lat = (float)$find['lat'];
            $lon = (float)$find['lon'];

            foreach ($candidatesByCode[$code] as $candidate) {
                $polygons = loadDeLormeBookPolygons($candidate['bookName'], $dataDir);
                $pageId = $candidate['id'];
                if (!isset($polygons[$pageId])) {
                    continue;
                }
                if (countyLookupPointInRing($lon, $lat, $polygons[$pageId])) {
                    $results[$code] = $candidate;
                    break;
                }
            }
        }

        return $results;
    }
}
