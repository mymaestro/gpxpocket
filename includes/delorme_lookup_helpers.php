<?php

if (!function_exists('loadDeLormePages')) {
    function loadDeLormePages($jsonPath, &$errorMessage) {
        if (!file_exists($jsonPath)) {
            $errorMessage = 'DeLorme pages dataset not found at ' . $jsonPath . '.';
            return array();
        }

        $raw = @file_get_contents($jsonPath);
        if ($raw === false || trim($raw) === '') {
            $errorMessage = 'Unable to read DeLorme pages file.';
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['pages']) || !is_array($decoded['pages'])) {
            $errorMessage = 'DeLorme pages file is not valid (missing pages array).';
            return array();
        }

        if (count($decoded['pages']) < 1) {
            $errorMessage = 'DeLorme pages file loaded but contains no pages.';
            return array();
        }

        return $decoded['pages'];
    }
}

if (!function_exists('findDeLormePageByPoint')) {
    // Requires county_lookup_helpers.php to be loaded first for countyLookupPointInRing.
    // Polygons in delorme-pages.json are stored as [lon, lat] pairs matching GeoJSON order.
    function findDeLormePageByPoint($lat, $lon, $pages) {
        $lat = (float)$lat;
        $lon = (float)$lon;

        foreach ($pages as $page) {
            // bbox prefilter: [lonMin, latMin, lonMax, latMax]
            if (isset($page['bbox']) && is_array($page['bbox']) && count($page['bbox']) === 4) {
                if ($lon < $page['bbox'][0] || $lon > $page['bbox'][2] ||
                    $lat < $page['bbox'][1] || $lat > $page['bbox'][3]) {
                    continue;
                }
            }

            if (isset($page['polygon']) && is_array($page['polygon'])) {
                if (countyLookupPointInRing($lon, $lat, $page['polygon'])) {
                    return $page;
                }
            }
        }

        return null;
    }
}
