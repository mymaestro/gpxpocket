<?php

if (!function_exists('cfXmlBool')) {
    function cfXmlBool($value, $default) {
        $text = strtolower(trim((string)$value));
        if ($text === '') {
            return (bool)$default;
        }
        if ($text === 'true' || $text === '1' || $text === 'yes') {
            return true;
        }
        if ($text === 'false' || $text === '0' || $text === 'no') {
            return false;
        }
        return (bool)$default;
    }
}

if (!function_exists('cfNormalizeRatingToHalfStep')) {
    function cfNormalizeRatingToHalfStep($value) {
        $numeric = (float)$value;
        if ($numeric <= 0) {
            return 0.0;
        }

        $rounded = round($numeric * 2) / 2;
        if ($rounded < 0.5) {
            $rounded = 0.5;
        }
        if ($rounded > 5.0) {
            $rounded = 5.0;
        }

        return $rounded;
    }
}

if (!function_exists('cfDtCellKey')) {
    function cfDtCellKey($difficulty, $terrain) {
        $d = cfNormalizeRatingToHalfStep($difficulty);
        $t = cfNormalizeRatingToHalfStep($terrain);
        if ($d <= 0 || $t <= 0) {
            return '';
        }
        return number_format($d, 1, '.', '') . '|' . number_format($t, 1, '.', '');
    }
}

if (!function_exists('cfAllDtCellKeys')) {
    function cfAllDtCellKeys() {
        $keys = array();
        for ($d = 0.5; $d <= 5.0001; $d += 0.5) {
            for ($t = 0.5; $t <= 5.0001; $t += 0.5) {
                $keys[] = number_format($d, 1, '.', '') . '|' . number_format($t, 1, '.', '');
            }
        }
        return $keys;
    }
}

if (!function_exists('parseRegionCachesFromGpx')) {
    function parseRegionCachesFromGpx($gpxPath, $displayName, &$message) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($gpxPath, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();

        if ($xml === false || strtolower($xml->getName()) !== 'gpx') {
            $message .= 'Could not parse region GPX: ' . $displayName . '. ';
            return array();
        }

        $rowsByCode = array();

        foreach ($xml->wpt as $wpt) {
            $cacheCode = trim((string)$wpt->name);
            $typeParts = explode('|', (string)$wpt->type);
            $cacheType = trim((string)$typeParts[0]);
            if ($cacheType !== 'Geocache' || $cacheCode === '') {
                continue;
            }

            $cacheInfo = $wpt->children('http://www.groundspeak.com/cache/1/0/1');
            if (!isset($cacheInfo->cache)) {
                continue;
            }

            $cacheNode = $cacheInfo->cache;
            $cacheAttrs = $cacheNode->attributes();
            $available = true;
            $archived = false;
            if ($cacheAttrs !== null) {
                $available = cfXmlBool(isset($cacheAttrs['available']) ? $cacheAttrs['available'] : '', true);
                $archived = cfXmlBool(isset($cacheAttrs['archived']) ? $cacheAttrs['archived'] : '', false);
            }

            $cacheName = trim((string)$wpt->urlname);
            $cacheUrl = trim((string)$wpt->url);
            $cacheLat = (float)$wpt['lat'];
            $cacheLon = (float)$wpt['lon'];
            $container = isset($cacheNode->container) ? trim((string)$cacheNode->container) : '';
            $difficulty = isset($cacheNode->difficulty) ? trim((string)$cacheNode->difficulty) : '';
            $terrain = isset($cacheNode->terrain) ? trim((string)$cacheNode->terrain) : '';
            $cacheKind = isset($cacheNode->type) ? trim((string)$cacheNode->type) : '';

            if (!isset($rowsByCode[$cacheCode])) {
                $rowsByCode[$cacheCode] = array(
                    'cacheCode' => $cacheCode,
                    'cacheName' => $cacheName,
                    'cacheUrl' => $cacheUrl,
                    'lat' => $cacheLat,
                    'lon' => $cacheLon,
                    'cacheType' => $cacheKind,
                    'container' => $container,
                    'difficulty' => $difficulty,
                    'terrain' => $terrain,
                    'available' => $available,
                    'archived' => $archived,
                    'sourceName' => $displayName,
                );
            }
        }

        return $rowsByCode;
    }
}

if (!function_exists('cfBuildCoverageFromMyFinds')) {
    function cfBuildCoverageFromMyFinds($myFindsByCode, $countyFeatures, $deLormePages, $deLormeDataDir) {
        $foundCountyByFips = array();
        $foundDelormeById = array();
        $foundDtByKey = array();

        foreach ($myFindsByCode as $find) {
            if (isset($find['difficulty']) && isset($find['terrain'])) {
                $dtKey = cfDtCellKey($find['difficulty'], $find['terrain']);
                if ($dtKey !== '') {
                    $foundDtByKey[$dtKey] = true;
                }
            }

            if (!empty($countyFeatures)) {
                $county = findCountyByPoint($find['lat'], $find['lon'], $countyFeatures);
                if ($county !== null && !empty($county['fips'])) {
                    $foundCountyByFips[$county['fips']] = array(
                        'fips' => $county['fips'],
                        'stateName' => $county['stateName'],
                        'countyName' => $county['countyName'],
                    );
                }
            }
        }

        if (!empty($deLormePages) && !empty($myFindsByCode)) {
            $matchedPagesByCache = matchDeLormePagesToFinds($myFindsByCode, $deLormePages, $deLormeDataDir);
            foreach ($matchedPagesByCache as $cacheCode => $pages) {
                foreach ($pages as $page) {
                    if (!empty($page['id'])) {
                        $foundDelormeById[$page['id']] = array(
                            'id' => $page['id'],
                            'bookName' => isset($page['bookName']) ? $page['bookName'] : '',
                            'stateName' => isset($page['stateName']) ? $page['stateName'] : '',
                            'page' => isset($page['page']) ? $page['page'] : '',
                        );
                    }
                }
            }
        }

        $allDt = cfAllDtCellKeys();
        $missingDtByKey = array();
        foreach ($allDt as $key) {
            if (!isset($foundDtByKey[$key])) {
                $missingDtByKey[$key] = true;
            }
        }

        return array(
            'foundCountyByFips' => $foundCountyByFips,
            'foundDelormeById' => $foundDelormeById,
            'foundDtByKey' => $foundDtByKey,
            'missingDtByKey' => $missingDtByKey,
        );
    }
}

if (!function_exists('cfScoreRegionCandidates')) {
    function cfScoreRegionCandidates($regionCachesByCode, $myFindsByCode, $coverage, $countyFeatures, $deLormePages, $deLormeDataDir) {
        $myFindCodes = array_fill_keys(array_keys($myFindsByCode), true);
        $foundCountyByFips = isset($coverage['foundCountyByFips']) ? $coverage['foundCountyByFips'] : array();
        $foundDelormeById = isset($coverage['foundDelormeById']) ? $coverage['foundDelormeById'] : array();
        $missingDtByKey = isset($coverage['missingDtByKey']) ? $coverage['missingDtByKey'] : array();

        $regionPoints = array();
        foreach ($regionCachesByCode as $code => $cache) {
            $regionPoints[$code] = array(
                'cacheCode' => $code,
                'lat' => $cache['lat'],
                'lon' => $cache['lon'],
            );
        }
        $matchedRegionDelorme = array();
        if (!empty($deLormePages) && !empty($regionPoints)) {
            $matchedRegionDelorme = matchDeLormePagesToFinds($regionPoints, $deLormePages, $deLormeDataDir);
        }

        $rows = array();
        foreach ($regionCachesByCode as $code => $cache) {
            if (isset($myFindCodes[$code])) {
                continue;
            }
            if (empty($cache['available']) || !empty($cache['archived'])) {
                continue;
            }

            $signals = array();

            if (!empty($countyFeatures)) {
                $county = findCountyByPoint($cache['lat'], $cache['lon'], $countyFeatures);
                if ($county !== null && !empty($county['fips']) && !isset($foundCountyByFips[$county['fips']])) {
                    $signals[] = array(
                        'kind' => 'county',
                        'label' => $county['countyName'] . ', ' . $county['stateName'],
                    );
                }
            }

            if (isset($matchedRegionDelorme[$code])) {
                foreach ($matchedRegionDelorme[$code] as $page) {
                    if (!empty($page['id']) && !isset($foundDelormeById[$page['id']])) {
                        $signals[] = array(
                            'kind' => 'delorme',
                            'label' => $page['bookName'] . ' ' . $page['page'],
                        );
                    }
                }
            }

            $dtKey = cfDtCellKey($cache['difficulty'], $cache['terrain']);
            if ($dtKey !== '' && isset($missingDtByKey[$dtKey])) {
                $signals[] = array(
                    'kind' => 'dt',
                    'label' => 'D/T ' . str_replace('|', '/', $dtKey),
                );
            }

            if (count($signals) < 1) {
                continue;
            }

            $rows[] = array(
                'cacheCode' => $cache['cacheCode'],
                'cacheName' => $cache['cacheName'],
                'cacheUrl' => $cache['cacheUrl'],
                'cacheType' => $cache['cacheType'],
                'difficulty' => $cache['difficulty'],
                'terrain' => $cache['terrain'],
                'container' => $cache['container'],
                'sourceName' => $cache['sourceName'],
                'lat' => $cache['lat'],
                'lon' => $cache['lon'],
                'score' => count($signals),
                'signals' => $signals,
            );
        }

        usort($rows, function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return strcasecmp($a['cacheCode'], $b['cacheCode']);
        });

        return $rows;
    }
}
