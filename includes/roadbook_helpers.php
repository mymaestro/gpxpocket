<?php

if (!function_exists('gpxRoadbookTruncateText')) {
    function gpxRoadbookTruncateText($value, $maxLength = 28) {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }
        $maxLength = (int)$maxLength;
        if ($maxLength <= 0) {
            return $text;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $length = mb_strlen($text, 'UTF-8');
            if ($length <= $maxLength) {
                return $text;
            }
            return mb_substr($text, 0, $maxLength - 1, 'UTF-8') . '…';
        }

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 1) . '…';
    }
}

if (!function_exists('gpxRoadbookFormatDdm')) {
    function gpxRoadbookFormatDdm($latitude, $longitude) {
        $lat = (float)$latitude;
        $lon = (float)$longitude;

        $latDir = $lat >= 0 ? 'N' : 'S';
        $lonDir = $lon >= 0 ? 'E' : 'W';

        $latAbs = abs($lat);
        $lonAbs = abs($lon);

        $latDeg = floor($latAbs);
        $lonDeg = floor($lonAbs);

        $latMin = ($latAbs - $latDeg) * 60;
        $lonMin = ($lonAbs - $lonDeg) * 60;

        return sprintf('%s %02d° %06.3f %s %03d° %06.3f', $latDir, $latDeg, $latMin, $lonDir, $lonDeg, $lonMin);
    }
}

if (!function_exists('gpxRoadbookComputeClusterBounds')) {
    function gpxRoadbookComputeClusterBounds($cluster) {
        $points = array();
        foreach ($cluster as $cache) {
            if (isset($cache['lat'], $cache['lon'])) {
                $points[] = array((float)$cache['lat'], (float)$cache['lon']);
            }
        }

        if (empty($points)) {
            return array(
                'minLat' => 0.0,
                'maxLat' => 0.0,
                'minLon' => 0.0,
                'maxLon' => 0.0,
                'centerLat' => 0.0,
                'centerLon' => 0.0,
            );
        }

        $minLat = min(array_map(function ($point) { return $point[0]; }, $points));
        $maxLat = max(array_map(function ($point) { return $point[0]; }, $points));
        $minLon = min(array_map(function ($point) { return $point[1]; }, $points));
        $maxLon = max(array_map(function ($point) { return $point[1]; }, $points));

        $latSpan = max(0.01, $maxLat - $minLat);
        $lonSpan = max(0.01, $maxLon - $minLon);

        $paddingLat = max(0.02, $latSpan * 0.28);
        $paddingLon = max(0.02, $lonSpan * 0.28);

        return array(
            'minLat' => $minLat - $paddingLat,
            'maxLat' => $maxLat + $paddingLat,
            'minLon' => $minLon - $paddingLon,
            'maxLon' => $maxLon + $paddingLon,
            'centerLat' => ($minLat + $maxLat) / 2,
            'centerLon' => ($minLon + $maxLon) / 2,
        );
    }
}

if (!function_exists('gpxRoadbookNormalizeLogEntry')) {
    function gpxRoadbookNormalizeLogEntry($rawLog) {
        if (!is_object($rawLog) && !is_array($rawLog)) {
            return array(
                'type' => '',
                'finder' => '',
                'date' => '',
                'text' => '',
            );
        }

        $result = array(
            'type' => '',
            'finder' => '',
            'date' => '',
            'text' => '',
        );

        if (is_object($rawLog)) {
            $rawLog = get_object_vars($rawLog);
        }

        if (isset($rawLog['type'])) {
            $result['type'] = trim((string)$rawLog['type']);
        }
        if (isset($rawLog['finder'])) {
            $result['finder'] = trim((string)$rawLog['finder']);
        }
        if (isset($rawLog['date'])) {
            $result['date'] = trim((string)$rawLog['date']);
        }
        if (isset($rawLog['text'])) {
            $result['text'] = trim((string)$rawLog['text']);
        }

        return $result;
    }
}

if (!function_exists('gpxRoadbookSummarizeLogs')) {
    function gpxRoadbookSummarizeLogs($logEntries, $finderStats = array()) {
        $summaries = array();
        foreach (array_slice($logEntries, 0, 5) as $log) {
            $entry = gpxRoadbookNormalizeLogEntry($log);
            $name = trim((string)$entry['finder']);
            $logType = trim((string)$entry['type']);
            $normalizedType = strtolower($logType);
            if ($normalizedType === 'found it') {
                $kind = 'find';
            } elseif ($normalizedType === 'did not find it' || $normalizedType === "didn't find it") {
                $kind = 'dnf';
            } else {
                $kind = 'other';
            }

            $text = trim(preg_replace('/\s+/', ' ', (string)$entry['text']));
            $text = preg_replace('/\s+/', ' ', $text);
            if ($text === '') {
                $text = 'No log text';
            }

            $count = '';
            if ($name !== '' && isset($finderStats[$name])) {
                $count = ' (' . (int)$finderStats[$name] . ')';
            }

            $summaries[] = array(
                'type' => $logType,
                'kind' => $kind,
                'finder' => $name,
                'finderCount' => $count,
                'snippet' => gpxRoadbookTruncateText($text, 42),
            );
        }

        return $summaries;
    }
}

if (!function_exists('gpxRoadbookConsecutiveDnfWarning')) {
    function gpxRoadbookConsecutiveDnfWarning($logEntries) {
        $dnfCount = 0;
        foreach ($logEntries as $log) {
            $entry = gpxRoadbookNormalizeLogEntry($log);
            $logType = strtolower(trim((string)$entry['type']));
            if ($logType === 'did not find it' || $logType === "didn't find it") {
                $dnfCount++;
            } else {
                break;
            }
        }

        if ($dnfCount >= 3) {
            return '⚠️ DNF ' . $dnfCount . ' times';
        }

        return '';
    }
}

if (!function_exists('gpxRoadbookParseGpxNode')) {
    function gpxRoadbookParseGpxNode($xml, $maxCaches = 50) {
        $cacheList = array();
        if (!$xml || !isset($xml->wpt)) {
            return $cacheList;
        }

        foreach ($xml->wpt as $wpt) {
            $cacheCode = trim((string)$wpt->name);
            $cacheType = '';
            $typeValue = trim((string)$wpt->type);
            if ($typeValue !== '') {
                $typeParts = explode('|', $typeValue);
                $cacheType = trim($typeParts[0]);
            }

            if ($cacheType !== 'Geocache') {
                continue;
            }

            $cacheInfo = $wpt->children('http://www.groundspeak.com/cache/1/0/1');
            $cacheNode = $cacheInfo->cache;
            if (!$cacheNode) {
                continue;
            }

            $logs = array();
            if (isset($cacheNode->logs) && isset($cacheNode->logs->log)) {
                foreach ($cacheNode->logs->log as $logNode) {
                    $logs[] = array(
                        'type' => trim((string)$logNode->type),
                        'finder' => trim((string)$logNode->finder),
                        'date' => trim((string)$logNode->date),
                        'text' => trim((string)$logNode->text),
                    );
                }
            }

            $cache = array(
                'code' => $cacheCode,
                'name' => trim((string)$wpt->urlname),
                'title' => trim((string)$wpt->urlname),
                'lat' => (float)$wpt['lat'],
                'lon' => (float)$wpt['lon'],
                'url' => trim((string)$wpt->url),
                'type' => $cacheType,
                'container' => trim((string)$cacheNode->container),
                'difficulty' => trim((string)$cacheNode->difficulty),
                'terrain' => trim((string)$cacheNode->terrain),
                'hint' => trim((string)$cacheNode->encoded_hints),
                'logs' => $logs,
                'state' => '',
                'county' => '',
            );

            $cacheList[] = $cache;
            if (count($cacheList) >= $maxCaches) {
                break;
            }
        }

        return $cacheList;
    }
}

if (!function_exists('gpxRoadbookResolvePlaceContext')) {
    function gpxRoadbookResolvePlaceContext($latitude, $longitude) {
        $lat = (float)$latitude;
        $lon = (float)$longitude;
        $context = array('state' => '', 'county' => '', 'display' => '');

        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . rawurlencode((string)$lat) . '&lon=' . rawurlencode((string)$lon);
        $stream = stream_context_create(array(
            'http' => array(
                'timeout' => 4,
                'user_agent' => 'gpxpocket-roadbook/1.0',
                'ignore_errors' => true,
            ),
        ));

        $response = @file_get_contents($url, false, $stream);
        if ($response === false || $response === '') {
            return $context;
        }

        $decoded = @json_decode($response, true);
        if (!is_array($decoded) || empty($decoded)) {
            return $context;
        }

        $address = isset($decoded['address']) && is_array($decoded['address']) ? $decoded['address'] : array();
        $state = isset($address['state']) ? trim((string)$address['state']) : '';
        $county = isset($address['county']) ? trim((string)$address['county']) : '';
        $city = isset($address['city']) ? trim((string)$address['city']) : '';
        $town = isset($address['town']) ? trim((string)$address['town']) : '';
        $village = isset($address['village']) ? trim((string)$address['village']) : '';
        $hamlet = isset($address['hamlet']) ? trim((string)$address['hamlet']) : '';

        $context['state'] = $state;
        $context['county'] = $county;
        $context['display'] = trim($city . ' ' . $town . ' ' . $village . ' ' . $hamlet);

        if ($context['display'] === '') {
            $context['display'] = trim((string)($decoded['display_name'] ?? ''));
        }

        if ($context['county'] === '' && $state !== '') {
            $context['county'] = $state;
        }

        return $context;
    }
}

if (!function_exists('gpxRoadbookClusterCaches')) {
    function gpxRoadbookClusterCaches($caches, $minClusterSize = 3, $maxClusterSize = 7) {
        $caches = array_values($caches);
        $total = count($caches);
        if ($total === 0) {
            return array();
        }

        $minClusterSize = max(1, (int)$minClusterSize);
        $maxClusterSize = max($minClusterSize, (int)$maxClusterSize);
        $clusterCount = max(1, (int)ceil($total / $maxClusterSize));
        if ($total >= $minClusterSize) {
            $clusterCount = min($clusterCount, (int)floor($total / $minClusterSize));
        }

        $distanceKm = function ($first, $second) {
            $earthRadiusKm = 6371.0;
            $latitudeDelta = deg2rad((float)$second['lat'] - (float)$first['lat']);
            $longitudeDelta = deg2rad((float)$second['lon'] - (float)$first['lon']);
            $firstLatitude = deg2rad((float)$first['lat']);
            $secondLatitude = deg2rad((float)$second['lat']);
            $haversine = sin($latitudeDelta / 2) * sin($latitudeDelta / 2)
                + cos($firstLatitude) * cos($secondLatitude)
                * sin($longitudeDelta / 2) * sin($longitudeDelta / 2);

            return 2 * $earthRadiusKm * asin(min(1, sqrt($haversine)));
        };

        $center = array('lat' => 0.0, 'lon' => 0.0);
        foreach ($caches as $cache) {
            $center['lat'] += (float)$cache['lat'] / $total;
            $center['lon'] += (float)$cache['lon'] / $total;
        }

        $unvisited = range(0, $total - 1);
        $nextCacheIndex = 0;
        $nearestCenterDistance = $distanceKm($caches[0], $center);
        foreach ($unvisited as $cacheIndex) {
            $distance = $distanceKm($caches[$cacheIndex], $center);
            if ($distance < $nearestCenterDistance) {
                $nextCacheIndex = $cacheIndex;
                $nearestCenterDistance = $distance;
            }
        }

        $orderedCaches = array();
        while (!empty($unvisited)) {
            $orderedCaches[] = $caches[$nextCacheIndex];
            $unvisited = array_values(array_diff($unvisited, array($nextCacheIndex)));

            if (empty($unvisited)) {
                break;
            }

            $nextCache = $unvisited[0];
            $nearestDistance = $distanceKm($caches[$nextCacheIndex], $caches[$nextCache]);
            foreach ($unvisited as $cacheIndex) {
                $distance = $distanceKm($caches[$nextCacheIndex], $caches[$cacheIndex]);
                if ($distance < $nearestDistance) {
                    $nextCache = $cacheIndex;
                    $nearestDistance = $distance;
                }
            }
            $nextCacheIndex = $nextCache;
        }

        $baseClusterSize = intdiv($total, $clusterCount);
        $largerClusterCount = $total % $clusterCount;
        $clusters = array();
        $start = 0;
        for ($clusterIndex = 0; $clusterIndex < $clusterCount; $clusterIndex++) {
            $clusterSize = $baseClusterSize + ($clusterIndex < $largerClusterCount ? 1 : 0);
            $clusters[] = array_slice($orderedCaches, $start, $clusterSize);
            $start += $clusterSize;
        }

        return $clusters;
    }
}
