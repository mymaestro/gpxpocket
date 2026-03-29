<?php

if (!function_exists('extractFindsFinderName')) {
    function extractFindsFinderName($log) {
        $finderName = '';

        if (isset($log->finder)) {
            $finderName = trim((string)$log->finder);
        }

        if ($finderName === '') {
            $logNs = $log->children('http://www.groundspeak.com/cache/1/0/1');
            if (isset($logNs->finder)) {
                $finderName = trim((string)$logNs->finder);
            }
        }

        return $finderName;
    }
}

if (!function_exists('isFoundLogType')) {
    function isFoundLogType($logType) {
        return strtolower(trim((string)$logType)) === 'found it';
    }
}

if (!function_exists('parseFoundCachesFromGpx')) {
    function parseFoundCachesFromGpx($gpxPath, $displayName, $targetUsername, &$message) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($gpxPath, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();

        if ($xml === false || strtolower($xml->getName()) !== 'gpx') {
            $message .= 'Could not parse GPX: ' . $displayName . '. ';
            return array();
        }

        $targetNorm = strtolower(trim((string)$targetUsername));
        $findsByCode = array();

        foreach ($xml->wpt as $wpt) {
            $cacheCode = trim((string)$wpt->name);
            $typeParts = explode('|', (string)$wpt->type);
            $cacheType = $typeParts[0];

            if ($cacheType !== 'Geocache' || $cacheCode === '') {
                continue;
            }

            $cacheInfo = $wpt->children('http://www.groundspeak.com/cache/1/0/1');
            if (!isset($cacheInfo->cache->logs) || !isset($cacheInfo->cache->logs->log)) {
                continue;
            }

            $cacheName = trim((string)$wpt->urlname);
            $cacheUrl = trim((string)$wpt->url);
            $cacheLat = (float)$wpt['lat'];
            $cacheLon = (float)$wpt['lon'];
            $cacheDifficulty = isset($cacheInfo->cache->difficulty) ? trim((string)$cacheInfo->cache->difficulty) : '';
            $cacheTerrain = isset($cacheInfo->cache->terrain) ? trim((string)$cacheInfo->cache->terrain) : '';

            $bestFindTs = null;
            $bestFindRaw = '';

            foreach ($cacheInfo->cache->logs->log as $log) {
                $logType = trim((string)$log->type);
                if (!isFoundLogType($logType)) {
                    continue;
                }

                $finderName = extractFindsFinderName($log);
                if ($finderName === '' || strtolower($finderName) !== $targetNorm) {
                    continue;
                }

                $dateRaw = trim((string)$log->date);
                $dateTs = strtotime($dateRaw);
                if ($dateTs === false) {
                    continue;
                }

                if ($bestFindTs === null || $dateTs < $bestFindTs) {
                    $bestFindTs = $dateTs;
                    $bestFindRaw = $dateRaw;
                }
            }

            if ($bestFindTs === null) {
                continue;
            }

            $findsByCode[$cacheCode] = array(
                'cacheCode' => $cacheCode,
                'cacheName' => $cacheName,
                'cacheUrl' => $cacheUrl,
                'lat' => $cacheLat,
                'lon' => $cacheLon,
                'difficulty' => $cacheDifficulty,
                'terrain' => $cacheTerrain,
                'firstFoundTs' => $bestFindTs,
                'firstFoundRaw' => $bestFindRaw,
                'sourceName' => $displayName,
            );
        }

        return $findsByCode;
    }
}
