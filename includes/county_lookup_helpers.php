<?php

if (!function_exists('countyLookupNormalizeFips')) {
    function countyLookupNormalizeFips($properties) {
        if (!is_array($properties)) {
            return '';
        }

        $candidates = array(
            isset($properties['GEOID']) ? $properties['GEOID'] : '',
            isset($properties['geoid']) ? $properties['geoid'] : '',
            isset($properties['FIPS']) ? $properties['FIPS'] : '',
            isset($properties['fips']) ? $properties['fips'] : '',
            isset($properties['id']) ? $properties['id'] : '',
            isset($properties['ID']) ? $properties['ID'] : '',
        );

        foreach ($candidates as $candidate) {
            $digits = preg_replace('/\D+/', '', (string)$candidate);
            if (strlen($digits) === 5) {
                return $digits;
            }
        }

        $stateFips = '';
        $countyFips = '';

        if (isset($properties['STATEFP'])) {
            $stateFips = preg_replace('/\D+/', '', (string)$properties['STATEFP']);
        } elseif (isset($properties['statefp'])) {
            $stateFips = preg_replace('/\D+/', '', (string)$properties['statefp']);
        }

        if (isset($properties['COUNTYFP'])) {
            $countyFips = preg_replace('/\D+/', '', (string)$properties['COUNTYFP']);
        } elseif (isset($properties['countyfp'])) {
            $countyFips = preg_replace('/\D+/', '', (string)$properties['countyfp']);
        }

        if ($stateFips !== '' && $countyFips !== '') {
            return str_pad($stateFips, 2, '0', STR_PAD_LEFT) . str_pad($countyFips, 3, '0', STR_PAD_LEFT);
        }

        return '';
    }
}

if (!function_exists('countyLookupStateNameFromFips')) {
    function countyLookupStateNameFromFips($stateFips) {
        $stateFips = str_pad(preg_replace('/\D+/', '', (string)$stateFips), 2, '0', STR_PAD_LEFT);

        $stateMap = array(
            '01' => 'Alabama',
            '02' => 'Alaska',
            '04' => 'Arizona',
            '05' => 'Arkansas',
            '06' => 'California',
            '08' => 'Colorado',
            '09' => 'Connecticut',
            '10' => 'Delaware',
            '11' => 'District of Columbia',
            '12' => 'Florida',
            '13' => 'Georgia',
            '15' => 'Hawaii',
            '16' => 'Idaho',
            '17' => 'Illinois',
            '18' => 'Indiana',
            '19' => 'Iowa',
            '20' => 'Kansas',
            '21' => 'Kentucky',
            '22' => 'Louisiana',
            '23' => 'Maine',
            '24' => 'Maryland',
            '25' => 'Massachusetts',
            '26' => 'Michigan',
            '27' => 'Minnesota',
            '28' => 'Mississippi',
            '29' => 'Missouri',
            '30' => 'Montana',
            '31' => 'Nebraska',
            '32' => 'Nevada',
            '33' => 'New Hampshire',
            '34' => 'New Jersey',
            '35' => 'New Mexico',
            '36' => 'New York',
            '37' => 'North Carolina',
            '38' => 'North Dakota',
            '39' => 'Ohio',
            '40' => 'Oklahoma',
            '41' => 'Oregon',
            '42' => 'Pennsylvania',
            '44' => 'Rhode Island',
            '45' => 'South Carolina',
            '46' => 'South Dakota',
            '47' => 'Tennessee',
            '48' => 'Texas',
            '49' => 'Utah',
            '50' => 'Vermont',
            '51' => 'Virginia',
            '53' => 'Washington',
            '54' => 'West Virginia',
            '55' => 'Wisconsin',
            '56' => 'Wyoming',
            '60' => 'American Samoa',
            '66' => 'Guam',
            '69' => 'Northern Mariana Islands',
            '72' => 'Puerto Rico',
            '78' => 'U.S. Virgin Islands',
        );

        return isset($stateMap[$stateFips]) ? $stateMap[$stateFips] : '';
    }
}

if (!function_exists('countyLookupResolveStateName')) {
    function countyLookupResolveStateName($properties, $fips) {
        $stateName = countyLookupPickName($properties, array('STATE_NAME', 'state_name', 'STATE', 'state', 'STUSPS', 'stusps'));

        if ($stateName !== '' && !preg_match('/^\d+$/', $stateName)) {
            return $stateName;
        }

        $stateFips = '';
        if (isset($properties['STATEFP'])) {
            $stateFips = (string)$properties['STATEFP'];
        } elseif (isset($properties['statefp'])) {
            $stateFips = (string)$properties['statefp'];
        } elseif (isset($properties['STATE'])) {
            $stateFips = (string)$properties['STATE'];
        } elseif (isset($properties['state'])) {
            $stateFips = (string)$properties['state'];
        } elseif (strlen((string)$fips) >= 2) {
            $stateFips = substr((string)$fips, 0, 2);
        }

        $resolved = countyLookupStateNameFromFips($stateFips);
        if ($resolved !== '') {
            return $resolved;
        }

        return $stateName;
    }
}

if (!function_exists('countyLookupPickName')) {
    function countyLookupPickName($properties, $keys) {
        if (!is_array($properties)) {
            return '';
        }

        foreach ($keys as $key) {
            if (isset($properties[$key])) {
                $value = trim((string)$properties[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('countyLookupComputeBboxFromRing')) {
    function countyLookupComputeBboxFromRing($ring) {
        if (!is_array($ring) || count($ring) < 3) {
            return null;
        }

        $minLon = null;
        $maxLon = null;
        $minLat = null;
        $maxLat = null;

        foreach ($ring as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }

            $lon = (float)$point[0];
            $lat = (float)$point[1];

            if ($minLon === null || $lon < $minLon) {
                $minLon = $lon;
            }
            if ($maxLon === null || $lon > $maxLon) {
                $maxLon = $lon;
            }
            if ($minLat === null || $lat < $minLat) {
                $minLat = $lat;
            }
            if ($maxLat === null || $lat > $maxLat) {
                $maxLat = $lat;
            }
        }

        if ($minLon === null || $minLat === null || $maxLon === null || $maxLat === null) {
            return null;
        }

        return array($minLon, $minLat, $maxLon, $maxLat);
    }
}

if (!function_exists('countyLookupMergeBbox')) {
    function countyLookupMergeBbox($bboxA, $bboxB) {
        if ($bboxA === null) {
            return $bboxB;
        }
        if ($bboxB === null) {
            return $bboxA;
        }

        return array(
            min($bboxA[0], $bboxB[0]),
            min($bboxA[1], $bboxB[1]),
            max($bboxA[2], $bboxB[2]),
            max($bboxA[3], $bboxB[3]),
        );
    }
}

if (!function_exists('countyLookupComputeGeometryBbox')) {
    function countyLookupComputeGeometryBbox($geometry) {
        if (!is_array($geometry) || !isset($geometry['type']) || !isset($geometry['coordinates'])) {
            return null;
        }

        $type = (string)$geometry['type'];
        $coordinates = $geometry['coordinates'];
        $bbox = null;

        if ($type === 'Polygon') {
            foreach ($coordinates as $ring) {
                $bbox = countyLookupMergeBbox($bbox, countyLookupComputeBboxFromRing($ring));
            }
            return $bbox;
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if (!is_array($polygon)) {
                    continue;
                }
                foreach ($polygon as $ring) {
                    $bbox = countyLookupMergeBbox($bbox, countyLookupComputeBboxFromRing($ring));
                }
            }
            return $bbox;
        }

        return null;
    }
}

if (!function_exists('countyLookupPointInRing')) {
    function countyLookupPointInRing($lon, $lat, $ring) {
        if (!is_array($ring) || count($ring) < 3) {
            return false;
        }

        $inside = false;
        $pointCount = count($ring);

        for ($i = 0, $j = $pointCount - 1; $i < $pointCount; $j = $i++) {
            if (!isset($ring[$i][0], $ring[$i][1], $ring[$j][0], $ring[$j][1])) {
                continue;
            }

            $xi = (float)$ring[$i][0];
            $yi = (float)$ring[$i][1];
            $xj = (float)$ring[$j][0];
            $yj = (float)$ring[$j][1];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lon < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi));

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

if (!function_exists('countyLookupPointInPolygon')) {
    function countyLookupPointInPolygon($lon, $lat, $polygonCoordinates) {
        if (!is_array($polygonCoordinates) || count($polygonCoordinates) < 1) {
            return false;
        }

        $outer = $polygonCoordinates[0];
        if (!countyLookupPointInRing($lon, $lat, $outer)) {
            return false;
        }

        $ringCount = count($polygonCoordinates);
        if ($ringCount < 2) {
            return true;
        }

        for ($i = 1; $i < $ringCount; $i++) {
            if (countyLookupPointInRing($lon, $lat, $polygonCoordinates[$i])) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('countyLookupPointInGeometry')) {
    function countyLookupPointInGeometry($lon, $lat, $geometry) {
        if (!is_array($geometry) || !isset($geometry['type']) || !isset($geometry['coordinates'])) {
            return false;
        }

        $type = (string)$geometry['type'];
        $coordinates = $geometry['coordinates'];

        if ($type === 'Polygon') {
            return countyLookupPointInPolygon($lon, $lat, $coordinates);
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygonCoordinates) {
                if (countyLookupPointInPolygon($lon, $lat, $polygonCoordinates)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('loadCountyGeojsonFeatures')) {
    function loadCountyGeojsonFeatures($geojsonPath, &$errorMessage) {
        if (!file_exists($geojsonPath)) {
            $errorMessage = 'County GeoJSON not found at ' . $geojsonPath . '.';
            return array();
        }

        $raw = @file_get_contents($geojsonPath);
        if ($raw === false || trim($raw) === '') {
            $errorMessage = 'Unable to read county GeoJSON file.';
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['features']) || !is_array($decoded['features'])) {
            $errorMessage = 'County GeoJSON is not a valid FeatureCollection.';
            return array();
        }

        $records = array();
        foreach ($decoded['features'] as $feature) {
            if (!is_array($feature) || !isset($feature['geometry'])) {
                continue;
            }

            $properties = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : array();
            if (isset($feature['id']) && !isset($properties['id'])) {
                $properties['id'] = $feature['id'];
            }
            $geometry = $feature['geometry'];
            $fips = countyLookupNormalizeFips($properties);

            if ($fips === '') {
                continue;
            }

            $countyName = countyLookupPickName($properties, array('NAME', 'name', 'COUNTY', 'county'));
            $stateName = countyLookupResolveStateName($properties, $fips);

            $records[$fips] = array(
                'fips' => $fips,
                'countyName' => $countyName,
                'stateName' => $stateName,
                'geometry' => $geometry,
                'bbox' => countyLookupComputeGeometryBbox($geometry),
            );
        }

        if (count($records) < 1) {
            $errorMessage = 'County GeoJSON loaded, but no county features with FIPS were found.';
            return array();
        }

        ksort($records);
        return array_values($records);
    }
}

if (!function_exists('findCountyByPoint')) {
    function findCountyByPoint($lat, $lon, $countyFeatures) {
        $lat = (float)$lat;
        $lon = (float)$lon;

        foreach ($countyFeatures as $feature) {
            if (!isset($feature['geometry'])) {
                continue;
            }

            if (isset($feature['bbox']) && is_array($feature['bbox']) && count($feature['bbox']) === 4) {
                if ($lon < $feature['bbox'][0] || $lon > $feature['bbox'][2] || $lat < $feature['bbox'][1] || $lat > $feature['bbox'][3]) {
                    continue;
                }
            }

            if (countyLookupPointInGeometry($lon, $lat, $feature['geometry'])) {
                return $feature;
            }
        }

        return null;
    }
}
