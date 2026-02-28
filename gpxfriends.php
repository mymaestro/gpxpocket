<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';

renderPageStart(array(
  'title' => 'Geocaching GPX Friends',
  'description' => 'Discover recurring cachers from Pocket Query GPX logs',
  'activeNav' => 'gpxfriends',
));

function extractFinderFromLog($log) {
    $finderName = '';
    $finderId = '';

    if (isset($log->finder)) {
        $finderName = trim((string)$log->finder);
        $finderId = trim((string)$log->finder['id']);

        if ($finderId === '') {
            $finderAttrs = $log->finder->attributes();
            if ($finderAttrs !== null && isset($finderAttrs['id'])) {
                $finderId = trim((string)$finderAttrs['id']);
            }
        }
    }

    if ($finderName === '' || $finderId === '') {
        $logNs = $log->children('http://www.groundspeak.com/cache/1/0/1');
        if (isset($logNs->finder)) {
            if ($finderName === '') {
                $finderName = trim((string)$logNs->finder);
            }

            if ($finderId === '') {
                $finderId = trim((string)$logNs->finder['id']);
                if ($finderId === '') {
                    $finderAttrs = $logNs->finder->attributes();
                    if ($finderAttrs !== null && isset($finderAttrs['id'])) {
                        $finderId = trim((string)$finderAttrs['id']);
                    }
                }
            }
        }
    }

    return array(
        'finderName' => $finderName,
        'finderId' => $finderId,
    );
}

function parseFriendsSnapshot($gpxPath, $displayName) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($gpxPath, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    if ($xml === false || strtolower($xml->getName()) !== 'gpx') {
        return null;
    }

    $gpxTimeRaw = trim((string)$xml->time);
    $gpxTimeTs = strtotime($gpxTimeRaw);
    if ($gpxTimeTs === false) {
        $gpxTimeTs = filemtime($gpxPath);
        $gpxTimeRaw = gmdate('c', $gpxTimeTs);
    }

    $events = array();
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

        foreach ($cacheInfo->cache->logs->log as $log) {
            $logId = getLogIdFromNode($log);
            if ($logId === '') {
                continue;
            }

            $finder = extractFinderFromLog($log);
            $finderName = $finder['finderName'];
            if ($finderName === '') {
                continue;
            }

            $finderId = $finder['finderId'];
            $logType = trim((string)$log->type);
            $dateRaw = trim((string)$log->date);
            $dateTs = strtotime($dateRaw);

            $events[] = array(
                'logId' => $logId,
                'finderId' => $finderId,
                'finderName' => $finderName,
                'logType' => $logType,
                'dateRaw' => $dateRaw,
                'dateTs' => $dateTs === false ? 0 : (int)$dateTs,
                'cacheCode' => $cacheCode,
                'cacheName' => $cacheName,
                'cacheUrl' => $cacheUrl,
            );
        }
    }

    return array(
        'displayName' => $displayName,
        'gpxTimeRaw' => $gpxTimeRaw,
        'gpxTimeTs' => $gpxTimeTs,
        'events' => $events,
    );
}

function bronKerboschPivot($rSet, $pSet, $xSet, $graph, $minSize, $maxResults, &$cliques, &$truncated) {
    if (count($cliques) >= $maxResults) {
        $truncated = true;
        return;
    }

    if (count($pSet) < 1 && count($xSet) < 1) {
        if (count($rSet) >= $minSize) {
            $cliques[] = array_keys($rSet);
            if (count($cliques) >= $maxResults) {
                $truncated = true;
            }
        }
        return;
    }

    $pivot = null;
    $pivotNeighborCount = -1;
    $union = $pSet + $xSet;
    foreach ($union as $node => $_present) {
        $neighbors = isset($graph[$node]) ? $graph[$node] : array();
        $countInP = count(array_intersect_key($pSet, $neighbors));
        if ($countInP > $pivotNeighborCount) {
            $pivotNeighborCount = $countInP;
            $pivot = $node;
        }
    }

    $candidates = $pSet;
    if ($pivot !== null && isset($graph[$pivot])) {
        $candidates = array_diff_key($pSet, $graph[$pivot]);
    }

    foreach (array_keys($candidates) as $candidateNode) {
        if (!isset($pSet[$candidateNode])) {
            continue;
        }

        $neighbors = isset($graph[$candidateNode]) ? $graph[$candidateNode] : array();
        $nextR = $rSet;
        $nextR[$candidateNode] = true;
        $nextP = array_intersect_key($pSet, $neighbors);
        $nextX = array_intersect_key($xSet, $neighbors);

        bronKerboschPivot($nextR, $nextP, $nextX, $graph, $minSize, $maxResults, $cliques, $truncated);
        if ($truncated) {
            return;
        }

        unset($pSet[$candidateNode]);
        $xSet[$candidateNode] = true;
    }
}

function findStrictCliques($graph, $minSize, $maxResults) {
    $pSet = array();
    foreach ($graph as $node => $_neighbors) {
        $pSet[$node] = true;
    }

    $cliques = array();
    $truncated = false;
    bronKerboschPivot(array(), $pSet, array(), $graph, $minSize, $maxResults, $cliques, $truncated);

    return array(
        'groups' => $cliques,
        'truncated' => $truncated,
    );
}

function finderProfileLink($finderName) {
    $name = trim((string)$finderName);
    if ($name === '') {
        return '—';
    }

    $profileUrl = 'https://www.geocaching.com/p/?u=' . rawurlencode($name);
    return '<a href="' . h($profileUrl) . '" target="_blank" rel="noopener">' . h($name) . '</a>';
}
?>

      <div class="headline">
        <h1>Discover recurring cachers from GPX logs</h1>
        <p class="lead">Upload one or more Pocket Query GPX/ZIP files to build a recurring finder leaderboard.</p>
      </div>

<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxUploadBytes = 10 * 1024 * 1024;
    $uploads = normalizeUploadArray(isset($_FILES['friendFiles']) ? $_FILES['friendFiles'] : array());
    $excludeFinder = trim((string)(isset($_POST['excludeFinder']) ? $_POST['excludeFinder'] : ''));
    $buddyHours = (int)(isset($_POST['buddyHours']) ? $_POST['buddyHours'] : 8);
    $groupMode = trim((string)(isset($_POST['groupMode']) ? $_POST['groupMode'] : 'connected'));
    $groupMinSize = (int)(isset($_POST['groupMinSize']) ? $_POST['groupMinSize'] : 3);
    $groupMinShared = (int)(isset($_POST['groupMinShared']) ? $_POST['groupMinShared'] : 2);
    if ($buddyHours < 1) {
        $buddyHours = 1;
    } elseif ($buddyHours > 168) {
        $buddyHours = 168;
    }
    if ($groupMinSize < 3) {
        $groupMinSize = 3;
    } elseif ($groupMinSize > 20) {
        $groupMinSize = 20;
    }
    if ($groupMinShared < 1) {
        $groupMinShared = 1;
    } elseif ($groupMinShared > 100) {
        $groupMinShared = 100;
    }
    if ($groupMode !== 'connected' && $groupMode !== 'strict') {
        $groupMode = 'connected';
    }
    $buddyWindowSeconds = $buddyHours * 3600;

    if (count($uploads) < 1) {
        echo '<div class="alert alert-danger" role="alert">Please upload at least 1 GPX/ZIP file.</div>';
    } else {
        $parsedUploads = array();
        $snapshots = array();

        foreach ($uploads as $upload) {
            $parsedUpload = extractGpxFromUpload($upload, $maxUploadBytes, $message);
            if (!$parsedUpload) {
                continue;
            }
            $parsedUploads[] = $parsedUpload;

            $snapshot = parseFriendsSnapshot($parsedUpload['source'], $parsedUpload['name']);
            if ($snapshot !== null) {
                $snapshots[] = $snapshot;
            } else {
                $message .= 'Could not parse GPX: ' . $parsedUpload['name'] . '. ';
            }
        }

        foreach ($parsedUploads as $parsedUpload) {
            cleanupExtracted($parsedUpload);
        }

        if (count($snapshots) < 1) {
            echo '<div class="alert alert-danger" role="alert">Need at least 1 valid GPX Pocket Query. ' . h($message) . '</div>';
        } else {
            usort($snapshots, function ($a, $b) {
                return $a['gpxTimeTs'] <=> $b['gpxTimeTs'];
            });

            $seenLogs = array();
            $finders = array();
            $totalEvents = 0;
            $cacheFoundEvents = array();

            foreach ($snapshots as $snapshot) {
                foreach ($snapshot['events'] as $event) {
                    $totalEvents++;

                    $logId = $event['logId'];
                    if (isset($seenLogs[$logId])) {
                        continue;
                    }
                    $seenLogs[$logId] = true;

                    if ($excludeFinder !== '' && strcasecmp($excludeFinder, $event['finderName']) === 0) {
                        continue;
                    }

                    $finderKey = ($event['finderId'] !== '')
                        ? ('id:' . $event['finderId'])
                        : ('name:' . strtolower($event['finderName']));

                    if (!isset($finders[$finderKey])) {
                        $finders[$finderKey] = array(
                            'finderId' => $event['finderId'],
                            'finderName' => $event['finderName'],
                            'logCount' => 0,
                            'foundItCount' => 0,
                            'cacheSet' => array(),
                            'lastSeenTs' => 0,
                            'lastSeenRaw' => '',
                        );
                    }

                    $finders[$finderKey]['logCount']++;
                    if (strcasecmp($event['logType'], 'Found it') === 0) {
                        $finders[$finderKey]['foundItCount']++;
                    }
                    $finders[$finderKey]['cacheSet'][$event['cacheCode']] = true;

                    if ($event['dateTs'] > $finders[$finderKey]['lastSeenTs']) {
                        $finders[$finderKey]['lastSeenTs'] = $event['dateTs'];
                        $finders[$finderKey]['lastSeenRaw'] = $event['dateRaw'];
                    }

                    if (strcasecmp($event['logType'], 'Found it') === 0) {
                        if (!isset($cacheFoundEvents[$event['cacheCode']])) {
                            $cacheFoundEvents[$event['cacheCode']] = array(
                                'cacheCode' => $event['cacheCode'],
                                'cacheName' => $event['cacheName'],
                                'cacheUrl' => $event['cacheUrl'],
                                'finders' => array(),
                            );
                        }

                        if (!isset($cacheFoundEvents[$event['cacheCode']]['finders'][$finderKey])) {
                            $cacheFoundEvents[$event['cacheCode']]['finders'][$finderKey] = array(
                                'finderId' => $event['finderId'],
                                'finderName' => $event['finderName'],
                                'dateTs' => $event['dateTs'],
                                'dateRaw' => $event['dateRaw'],
                            );
                        } else {
                            $existingTs = $cacheFoundEvents[$event['cacheCode']]['finders'][$finderKey]['dateTs'];
                            if ($event['dateTs'] > 0 && ($existingTs < 1 || $event['dateTs'] < $existingTs)) {
                                $cacheFoundEvents[$event['cacheCode']]['finders'][$finderKey]['dateTs'] = $event['dateTs'];
                                $cacheFoundEvents[$event['cacheCode']]['finders'][$finderKey]['dateRaw'] = $event['dateRaw'];
                            }
                        }
                    }
                }
            }

            $rows = array();
            foreach ($finders as $finder) {
                $finder['uniqueCaches'] = count($finder['cacheSet']);
                unset($finder['cacheSet']);
                $rows[] = $finder;
            }

            usort($rows, function ($a, $b) {
                if ($a['logCount'] !== $b['logCount']) {
                    return $b['logCount'] <=> $a['logCount'];
                }
                if ($a['uniqueCaches'] !== $b['uniqueCaches']) {
                    return $b['uniqueCaches'] <=> $a['uniqueCaches'];
                }
                if ($a['lastSeenTs'] !== $b['lastSeenTs']) {
                    return $b['lastSeenTs'] <=> $a['lastSeenTs'];
                }
                return strcasecmp($a['finderName'], $b['finderName']);
            });

            $pairStats = array();
            foreach ($cacheFoundEvents as $cacheEvent) {
                $finderMap = $cacheEvent['finders'];
                if (count($finderMap) < 2) {
                    continue;
                }

                $finderKeys = array_keys($finderMap);
                $finderCount = count($finderKeys);
                for ($i = 0; $i < $finderCount - 1; $i++) {
                    $leftKey = $finderKeys[$i];
                    $left = $finderMap[$leftKey];

                    for ($j = $i + 1; $j < $finderCount; $j++) {
                        $rightKey = $finderKeys[$j];
                        $right = $finderMap[$rightKey];

                        if ($left['dateTs'] < 1 || $right['dateTs'] < 1) {
                            continue;
                        }

                        $deltaSeconds = abs($left['dateTs'] - $right['dateTs']);
                        if ($deltaSeconds > $buddyWindowSeconds) {
                            continue;
                        }

                        $pairKeys = array($leftKey, $rightKey);
                        sort($pairKeys);
                        $pairKey = $pairKeys[0] . '|' . $pairKeys[1];

                        $finderA = $finderMap[$pairKeys[0]];
                        $finderB = $finderMap[$pairKeys[1]];

                        if (!isset($pairStats[$pairKey])) {
                            $pairStats[$pairKey] = array(
                                'finderKeyA' => $pairKeys[0],
                                'finderKeyB' => $pairKeys[1],
                                'leftName' => $finderA['finderName'],
                                'leftId' => $finderA['finderId'],
                                'rightName' => $finderB['finderName'],
                                'rightId' => $finderB['finderId'],
                                'sharedCount' => 0,
                                'sameDayCount' => 0,
                                'closestGapSeconds' => 0,
                                'lastTogetherTs' => 0,
                                'examples' => array(),
                            );
                        }

                        $pairStats[$pairKey]['sharedCount']++;

                        $leftDay = gmdate('Y-m-d', $left['dateTs']);
                        $rightDay = gmdate('Y-m-d', $right['dateTs']);
                        if ($leftDay === $rightDay) {
                            $pairStats[$pairKey]['sameDayCount']++;
                        }

                        if ($pairStats[$pairKey]['closestGapSeconds'] < 1 || $deltaSeconds < $pairStats[$pairKey]['closestGapSeconds']) {
                            $pairStats[$pairKey]['closestGapSeconds'] = $deltaSeconds;
                        }

                        $togetherTs = max($left['dateTs'], $right['dateTs']);
                        if ($togetherTs > $pairStats[$pairKey]['lastTogetherTs']) {
                            $pairStats[$pairKey]['lastTogetherTs'] = $togetherTs;
                        }

                        if (count($pairStats[$pairKey]['examples']) < 3) {
                            $pairStats[$pairKey]['examples'][] = array(
                                'cacheCode' => $cacheEvent['cacheCode'],
                                'cacheName' => $cacheEvent['cacheName'],
                                'cacheUrl' => $cacheEvent['cacheUrl'],
                                'dateRaw' => $left['dateRaw'],
                                'deltaSeconds' => $deltaSeconds,
                            );
                        }
                    }
                }
            }

            $pairRows = array_values($pairStats);
            usort($pairRows, function ($a, $b) {
                if ($a['sharedCount'] !== $b['sharedCount']) {
                    return $b['sharedCount'] <=> $a['sharedCount'];
                }
                if ($a['sameDayCount'] !== $b['sameDayCount']) {
                    return $b['sameDayCount'] <=> $a['sameDayCount'];
                }
                if ($a['lastTogetherTs'] !== $b['lastTogetherTs']) {
                    return $b['lastTogetherTs'] <=> $a['lastTogetherTs'];
                }
                $aName = $a['leftName'] . ' + ' . $a['rightName'];
                $bName = $b['leftName'] . ' + ' . $b['rightName'];
                return strcasecmp($aName, $bName);
            });

            $finderProfiles = array();
            foreach ($finders as $finderKey => $finder) {
                $finderProfiles[$finderKey] = array(
                    'finderName' => $finder['finderName'],
                    'finderId' => $finder['finderId'],
                );
            }

            $graph = array();
            $edgeStats = array();
            foreach ($pairRows as $pairRow) {
                if ($pairRow['sharedCount'] < $groupMinShared) {
                    continue;
                }

                $aKey = $pairRow['finderKeyA'];
                $bKey = $pairRow['finderKeyB'];

                if (!isset($graph[$aKey])) {
                    $graph[$aKey] = array();
                }
                if (!isset($graph[$bKey])) {
                    $graph[$bKey] = array();
                }
                $graph[$aKey][$bKey] = true;
                $graph[$bKey][$aKey] = true;

                $edgeKey = $aKey . '|' . $bKey;
                $edgeStats[$edgeKey] = $pairRow;
            }

            $groupRows = array();
            $groupModeLabel = ($groupMode === 'strict') ? 'Strict clique' : 'Connected components';
            $groupModeDescription = ($groupMode === 'strict')
                ? 'Strict mode: every member pair must be linked by a buddy edge.'
                : 'Connected mode: members can be linked through chains of buddy edges.';
            $strictTruncated = false;

            $groupMembersList = array();
            if ($groupMode === 'strict') {
                $cliqueResult = findStrictCliques($graph, $groupMinSize, 200);
                $strictTruncated = $cliqueResult['truncated'];
                $groupMembersList = $cliqueResult['groups'];
            } else {
                $visited = array();
                foreach ($graph as $startKey => $_neighbors) {
                    if (isset($visited[$startKey])) {
                        continue;
                    }

                    $queue = array($startKey);
                    $visited[$startKey] = true;
                    $component = array();

                    while (count($queue) > 0) {
                        $current = array_shift($queue);
                        $component[] = $current;

                        foreach ($graph[$current] as $neighborKey => $_hasEdge) {
                            if (!isset($visited[$neighborKey])) {
                                $visited[$neighborKey] = true;
                                $queue[] = $neighborKey;
                            }
                        }
                    }

                    if (count($component) >= $groupMinSize) {
                        $groupMembersList[] = $component;
                    }
                }
            }

            $seenGroupKeys = array();
            foreach ($groupMembersList as $component) {
                $memberCount = count($component);
                if ($memberCount < $groupMinSize) {
                    continue;
                }

                usort($component, function ($a, $b) use ($finderProfiles) {
                    $aName = isset($finderProfiles[$a]) ? $finderProfiles[$a]['finderName'] : $a;
                    $bName = isset($finderProfiles[$b]) ? $finderProfiles[$b]['finderName'] : $b;
                    return strcasecmp($aName, $bName);
                });

                $groupKey = implode('|', $component);
                if (isset($seenGroupKeys[$groupKey])) {
                    continue;
                }
                $seenGroupKeys[$groupKey] = true;

                $edgeCount = 0;
                $sharedEdgeSum = 0;
                $lastTogetherTs = 0;
                $strongestPair = null;

                for ($i = 0; $i < $memberCount - 1; $i++) {
                    for ($j = $i + 1; $j < $memberCount; $j++) {
                        $a = $component[$i];
                        $b = $component[$j];
                        $pairKeys = array($a, $b);
                        sort($pairKeys);
                        $edgeKey = $pairKeys[0] . '|' . $pairKeys[1];
                        if (!isset($edgeStats[$edgeKey])) {
                            continue;
                        }

                        $edge = $edgeStats[$edgeKey];
                        $edgeCount++;
                        $sharedEdgeSum += $edge['sharedCount'];
                        if ($edge['lastTogetherTs'] > $lastTogetherTs) {
                            $lastTogetherTs = $edge['lastTogetherTs'];
                        }

                        if ($strongestPair === null || $edge['sharedCount'] > $strongestPair['sharedCount']) {
                            $strongestPair = $edge;
                        }
                    }
                }

                $possibleEdges = ($memberCount * ($memberCount - 1)) / 2;
                $density = $possibleEdges > 0 ? ($edgeCount / $possibleEdges) : 0;

                $memberLabels = array();
                foreach ($component as $memberKey) {
                    $memberName = isset($finderProfiles[$memberKey]) ? $finderProfiles[$memberKey]['finderName'] : $memberKey;
                    $memberLabels[] = $memberName;
                }

                $groupRows[] = array(
                    'memberCount' => $memberCount,
                    'members' => $memberLabels,
                    'edgeCount' => $edgeCount,
                    'possibleEdges' => $possibleEdges,
                    'density' => $density,
                    'sharedEdgeSum' => $sharedEdgeSum,
                    'lastTogetherTs' => $lastTogetherTs,
                    'strongestPair' => $strongestPair,
                );
            }

            usort($groupRows, function ($a, $b) {
                if ($a['memberCount'] !== $b['memberCount']) {
                    return $b['memberCount'] <=> $a['memberCount'];
                }
                if ($a['density'] !== $b['density']) {
                    return $b['density'] <=> $a['density'];
                }
                if ($a['sharedEdgeSum'] !== $b['sharedEdgeSum']) {
                    return $b['sharedEdgeSum'] <=> $a['sharedEdgeSum'];
                }
                if ($a['lastTogetherTs'] !== $b['lastTogetherTs']) {
                    return $b['lastTogetherTs'] <=> $a['lastTogetherTs'];
                }
                return 0;
            });

            if ($strictTruncated) {
                $message .= 'Strict clique mode reached the 200-group limit; showing the top discovered groups. ';
            }

            $clusterRows = array();

            foreach ($pairRows as $pairRow) {
                $leftLabel = $pairRow['leftName'];
                $rightLabel = $pairRow['rightName'];

                $exampleParts = array();
                foreach ($pairRow['examples'] as $example) {
                    $label = ($example['cacheName'] !== '' ? $example['cacheName'] : $example['cacheCode']);
                    $gap = number_format($example['deltaSeconds'] / 3600, 1) . 'h';
                    if ($example['cacheUrl'] !== '') {
                        $exampleParts[] = '<a href="' . h($example['cacheUrl']) . '" target="_blank" rel="noopener">' . h($label) . '</a> (' . h($gap) . ')';
                    } else {
                        $exampleParts[] = h($label) . ' (' . h($gap) . ')';
                    }
                }

                $clusterRows[] = array(
                    'kind' => 'Pair',
                    'membersLabel' => finderProfileLink($leftLabel) . ' + ' . finderProfileLink($rightLabel),
                    'membersSortLabel' => $leftLabel . ' + ' . $rightLabel,
                    'memberCount' => 2,
                    'connectedPairsLabel' => '1/1',
                    'density' => 1,
                    'pairSharedSum' => (int)$pairRow['sharedCount'],
                    'lastTogetherTs' => (int)$pairRow['lastTogetherTs'],
                    'detailsHtml' => 'Same day: ' . (int)$pairRow['sameDayCount']
                        . '; Closest gap: ' . h($pairRow['closestGapSeconds'] > 0 ? number_format($pairRow['closestGapSeconds'] / 3600, 1) . 'h' : '—')
                        . (count($exampleParts) > 0 ? '; Examples: ' . implode('; ', $exampleParts) : ''),
                );
            }

            foreach ($groupRows as $groupRow) {
                $strongestPairHtml = '—';
                $groupExampleParts = array();
                if ($groupRow['strongestPair'] !== null) {
                    $strongestPairHtml = finderProfileLink($groupRow['strongestPair']['leftName']) . ' + '
                        . finderProfileLink($groupRow['strongestPair']['rightName'])
                        . ' (' . (int)$groupRow['strongestPair']['sharedCount'] . ')';

                    if (isset($groupRow['strongestPair']['examples']) && is_array($groupRow['strongestPair']['examples'])) {
                        foreach ($groupRow['strongestPair']['examples'] as $example) {
                            $label = ($example['cacheName'] !== '' ? $example['cacheName'] : $example['cacheCode']);
                            $gap = number_format($example['deltaSeconds'] / 3600, 1) . 'h';
                            if ($example['cacheUrl'] !== '') {
                                $groupExampleParts[] = '<a href="' . h($example['cacheUrl']) . '" target="_blank" rel="noopener">' . h($label) . '</a> (' . h($gap) . ')';
                            } else {
                                $groupExampleParts[] = h($label) . ' (' . h($gap) . ')';
                            }
                        }
                    }
                }

                $groupMemberLinks = array();
                foreach ($groupRow['members'] as $memberName) {
                    $groupMemberLinks[] = finderProfileLink($memberName);
                }

                $clusterRows[] = array(
                    'kind' => 'Group',
                    'membersLabel' => implode(', ', $groupMemberLinks),
                    'membersSortLabel' => implode(', ', $groupRow['members']),
                    'memberCount' => (int)$groupRow['memberCount'],
                    'connectedPairsLabel' => (int)$groupRow['edgeCount'] . '/' . (int)$groupRow['possibleEdges'],
                    'density' => (float)$groupRow['density'],
                    'pairSharedSum' => (int)$groupRow['sharedEdgeSum'],
                    'lastTogetherTs' => (int)$groupRow['lastTogetherTs'],
                    'detailsHtml' => 'Strongest pair: ' . $strongestPairHtml
                        . (count($groupExampleParts) > 0 ? '; Examples: ' . implode('; ', $groupExampleParts) : ''),
                );
            }

            usort($clusterRows, function ($a, $b) {
                if ($a['memberCount'] !== $b['memberCount']) {
                    return $b['memberCount'] <=> $a['memberCount'];
                }
                if ($a['density'] !== $b['density']) {
                    return $b['density'] <=> $a['density'];
                }
                if ($a['pairSharedSum'] !== $b['pairSharedSum']) {
                    return $b['pairSharedSum'] <=> $a['pairSharedSum'];
                }
                if ($a['lastTogetherTs'] !== $b['lastTogetherTs']) {
                    return $b['lastTogetherTs'] <=> $a['lastTogetherTs'];
                }
                return strcasecmp($a['membersSortLabel'], $b['membersSortLabel']);
            });

            echo '<div class="alert alert-success" role="alert">Processed ' . count($snapshots) . ' Pocket Queries and ' . count($seenLogs) . ' unique logs. Found ' . count($rows) . ' distinct cachers.</div>';
            if ($message !== '') {
                echo '<div class="alert alert-warning" role="alert">' . h($message) . '</div>';
            }

            echo '<div class="card mb-4">';
            echo '  <div class="card-body">';
            echo '    <h5 class="card-title mb-1">Connection clusters</h5>';
            echo '    <p class="text-muted small mb-3">Unified view of pairs and groups. Buddy window: ' . (int)$buddyHours . 'h. Group mode: ' . h($groupModeLabel) . '.</p>';

            if (count($clusterRows) < 1) {
                echo '    <div class="alert alert-secondary mb-0" role="alert">No pair or group clusters found for current thresholds.</div>';
            } else {
                echo '    <div class="row g-3">';

                foreach ($clusterRows as $clusterRow) {
                    $densityPct = number_format($clusterRow['density'] * 100, 1) . '%';
                    $kindIconHtml = '<i class="bi bi-people" aria-hidden="true"></i>';
                    $kindTitle = 'Pair';
                    if ($clusterRow['kind'] === 'Group') {
                        $kindIconHtml = '<i class="bi bi-people-fill" aria-hidden="true"></i>';
                        $kindTitle = 'Group';
                    }

                    echo '      <div class="col-12 col-xl-6">';
                    echo '      <div class="border rounded p-3 bg-light h-100">';
                    echo '        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">';
                    echo '          <div>';
                    echo '            <div class="fw-semibold mb-1">' . $kindIconHtml . ' ' . h($kindTitle) . '</div>';
                    echo '            <div>' . $clusterRow['membersLabel'] . '</div>';
                    echo '          </div>';
                    echo '          <div class="text-end small text-muted">Last activity<br>' . h($clusterRow['lastTogetherTs'] > 0 ? gmdate('Y-m-d', $clusterRow['lastTogetherTs']) : '—') . '</div>';
                    echo '        </div>';
                    echo '        <div class="d-flex flex-wrap gap-2 mt-3">';
                    echo '          <span class="badge text-bg-secondary">Size: ' . (int)$clusterRow['memberCount'] . '</span>';
                    echo '          <span class="badge text-bg-secondary">Connected: ' . h($clusterRow['connectedPairsLabel']) . '</span>';
                    echo '          <span class="badge text-bg-secondary">Density: ' . h($densityPct) . '</span>';
                    echo '          <span class="badge text-bg-secondary">Pair sum: ' . (int)$clusterRow['pairSharedSum'] . '</span>';
                    echo '        </div>';
                    echo '        <div class="small mt-3">' . $clusterRow['detailsHtml'] . '</div>';
                    echo '      </div>';
                    echo '      </div>';
                }

                echo '    </div>';
            }

            echo '  </div>';
            echo '</div>';

            echo '<div class="card mb-4">';
            echo '  <div class="card-body">';
            echo '    <h5 class="card-title">Run details</h5>';
            echo '    <ul class="mb-0">';
            echo '      <li>Total parsed log events (before dedup): ' . (int)$totalEvents . '</li>';
            echo '      <li>Unique logs by log ID: ' . count($seenLogs) . '</li>';
            echo '      <li>Buddy time window: ' . (int)$buddyHours . ' hour(s)</li>';
            echo '      <li>Buddy pairs found: ' . count($pairRows) . '</li>';
            echo '      <li>Total clusters shown: ' . count($clusterRows) . '</li>';
            echo '      <li>Group mode: ' . h($groupModeLabel) . '</li>';
            echo '      <li>Group threshold: at least ' . (int)$groupMinSize . ' members with pair shared-finds ≥ ' . (int)$groupMinShared . '</li>';
            echo '      <li>Groups found: ' . count($groupRows) . '</li>';
            if ($strictTruncated) {
                echo '      <li>Strict clique result limit hit: 200 groups</li>';
            }
            if ($excludeFinder !== '') {
                echo '      <li>Excluded finder name: ' . h($excludeFinder) . '</li>';
            }
            echo '    </ul>';
            echo '  </div>';
            echo '</div>';
        }
    }
} else {
        ?>
        <div class="headline">
            <form action="gpxfriends.php" method="post" enctype="multipart/form-data" id="friendsForm" class="mx-auto" style="max-width: 900px;">
                <input type="file" name="friendFiles[]" id="friendFiles" class="d-none" accept=".gpx,.zip" multiple>
                <div id="friendsDropZone" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload GPX or zip files">
                    <div class="h5 mb-2">Drop one or more GPX/ZIP files here</div>
                    <div class="text-muted mb-0">or click to choose files</div>
                    <div id="friendsSelected" class="small mt-3 text-dark"></div>
                </div>
                <div class="form-group mt-3 text-start">
                    <label for="excludeFinder">Exclude finder name (optional)</label>
                    <input type="text" class="form-control" id="excludeFinder" name="excludeFinder" placeholder="e.g., myusername">
                    <div class="small text-muted mt-1">Case-insensitive exact match.</div>
                </div>
                <div class="small text-start mt-3">
                    Looking for rankings only? <a href="./gpxleaderboard.php">Open GPX Leaderboard page</a>.
                </div>
                <div class="form-group mt-3 text-start">
                    <label for="buddyHours">Buddy time window (hours)</label>
                    <input type="number" class="form-control" id="buddyHours" name="buddyHours" min="1" max="168" value="8">
                    <div class="small text-muted mt-1">Two finders are considered together when they logged the same cache within this time window.</div>
                </div>
                <div class="form-group mt-3 text-start">
                    <label for="groupMode">Group mode</label>
                    <select class="form-select" id="groupMode" name="groupMode">
                        <option value="connected" selected>Connected (broader)</option>
                        <option value="strict">Strict clique (all linked)</option>
                    </select>
                    <div class="small text-muted mt-1">Connected mode allows chain links. Strict mode requires every member pair to be linked.</div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="groupMinSize">Minimum group size</label>
                        <input type="number" class="form-control" id="groupMinSize" name="groupMinSize" min="3" max="20" value="3">
                    </div>
                    <div class="col-md-6">
                        <label for="groupMinShared">Min shared finds per pair</label>
                        <input type="number" class="form-control" id="groupMinShared" name="groupMinShared" min="1" max="100" value="2">
                    </div>
                </div>
                <button id="friendsSubmit" type="submit" class="btn btn-primary mt-2" disabled>Build Friends Clusters</button>
            </form>
        </div>
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Friends Feature</h5>
                        <p class="card-text mb-3">This page focuses on co-find clusters and likely caching groups from unique log IDs.</p>
                        <ul class="mb-0">
                            <li>Input: one or more GPX/ZIP files</li>
                            <li>Core dedup: global by log <code>id</code></li>
                            <li>Grouping key: finder <code>id</code> (or finder name fallback)</li>
                            <li>Buddy detection: shared cache + close log timestamps</li>
                            <li>Group detection: toggle between connected and strict clique modes</li>
                            <li>Leaderboards now live on <a href="./gpxleaderboard.php">GPX Leaderboard</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Planning docs</h5>
                        <p class="card-text mb-2">Feature planning lives in:</p>
                        <ul class="mb-0">
                            <li><a href="./files/CACHING_FRIENDS_FEATURE_PLAN.md">CACHING_FRIENDS_FEATURE_PLAN.md</a></li>
                            <li><a href="./files/AI_FINDS_INSIGHTS_MVP.md">AI_FINDS_INSIGHTS_MVP.md</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
}
?>

<script>
$(function () {
  var $form = $('#friendsForm');
  var $input = $('#friendFiles');
  var $dropZone = $('#friendsDropZone');
  var $selected = $('#friendsSelected');
  var $submit = $('#friendsSubmit');

  if (!$form.length || !$input.length || !$dropZone.length) {
    return;
  }

  function updateState() {
    var files = $input[0].files || [];
    if (files.length) {
      var names = [];
      for (var i = 0; i < files.length; i++) {
        names.push(files[i].name);
      }
      $selected.text(files.length + ' file(s): ' + names.join(', '));
    } else {
      $selected.text('');
    }
    $submit.prop('disabled', files.length < 1);
  }

    $form.on('submit', function (event) {
        if ($form.data('submitting')) {
            event.preventDefault();
            return;
        }

        $form.data('submitting', true);
        $submit.prop('disabled', true);
        $submit.attr('aria-busy', 'true');
        $submit.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...');
    });

  $dropZone.on('click', function () { $input.trigger('click'); });
  $dropZone.on('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      $input.trigger('click');
    }
  });

  $input.on('change', updateState);

  $dropZone.on('dragenter dragover', function (event) {
    event.preventDefault();
    event.stopPropagation();
    $dropZone.addClass('dropzone-active border-primary');
  });

  $dropZone.on('dragleave dragend drop', function (event) {
    event.preventDefault();
    event.stopPropagation();
    $dropZone.removeClass('dropzone-active border-primary');
  });

  $dropZone.on('drop', function (event) {
    var files = event.originalEvent.dataTransfer ? event.originalEvent.dataTransfer.files : null;
    if (!files || !files.length) {
      return;
    }
    var dataTransfer = new DataTransfer();
    for (var i = 0; i < files.length; i++) {
      dataTransfer.items.add(files[i]);
    }
    $input[0].files = dataTransfer.files;
    updateState();
  });
});
</script>

<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxfriends.php')); ?>
