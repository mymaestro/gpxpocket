<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';

renderPageStart(array(
  'title' => 'Geocaching GPX Leaderboard',
  'description' => 'Recurring finder leaderboard from Pocket Query GPX logs',
  'activeNav' => 'gpxleaderboard',
));

function extractLeaderboardFinderFromLog($log) {
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

function parseLeaderboardSnapshot($gpxPath, $displayName) {
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

        foreach ($cacheInfo->cache->logs->log as $log) {
            $logId = getLogIdFromNode($log);
            if ($logId === '') {
                continue;
            }

            $finder = extractLeaderboardFinderFromLog($log);
            $finderName = $finder['finderName'];
            if ($finderName === '') {
                continue;
            }

            $events[] = array(
                'logId' => $logId,
                'finderId' => $finder['finderId'],
                'finderName' => $finderName,
                'logType' => trim((string)$log->type),
                'dateRaw' => trim((string)$log->date),
                'dateTs' => (strtotime((string)$log->date) === false ? 0 : (int)strtotime((string)$log->date)),
                'cacheCode' => $cacheCode,
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

function leaderboardFinderLink($finderName) {
    $name = trim((string)$finderName);
    if ($name === '') {
        return '—';
    }

    $profileUrl = 'https://www.geocaching.com/p/?u=' . rawurlencode($name);
    return '<a href="' . h($profileUrl) . '" target="_blank" rel="noopener">' . h($name) . '</a>';
}
?>

<div class="headline">
  <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/gpx.png">
  <h1>Recurring finder leaderboard</h1>
  <p class="lead">Upload one or more Pocket Query GPX/ZIP files to rank recurring finders.</p>
</div>

<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxUploadBytes = 10 * 1024 * 1024;
    $uploads = normalizeUploadArray(isset($_FILES['leaderboardFiles']) ? $_FILES['leaderboardFiles'] : array());
    $excludeFinder = trim((string)(isset($_POST['excludeFinder']) ? $_POST['excludeFinder'] : ''));

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

            $snapshot = parseLeaderboardSnapshot($parsedUpload['source'], $parsedUpload['name']);
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

            echo '<div class="alert alert-success" role="alert">Processed ' . count($snapshots) . ' Pocket Queries and ' . count($seenLogs) . ' unique logs. Found ' . count($rows) . ' distinct cachers.</div>';
            if ($message !== '') {
                echo '<div class="alert alert-warning" role="alert">' . h($message) . '</div>';
            }

            echo '<div class="card mb-4">';
            echo '  <div class="card-body">';
            echo '    <h5 class="card-title mb-3">Finder leaderboard</h5>';
            echo '    <div class="table-responsive">';
            echo '      <table class="table table-striped table-sm">';
            echo '        <thead><tr>';
            echo '          <th>#</th>';
            echo '          <th>Finder</th>';
            echo '          <th class="text-end">Logs</th>';
            echo '          <th class="text-end">Found it logs</th>';
            echo '          <th class="text-end">Unique caches</th>';
            echo '          <th>Last seen</th>';
            echo '        </tr></thead><tbody>';

            $rank = 1;
            foreach ($rows as $row) {
                echo '<tr>';
                echo '  <td>' . $rank . '</td>';
                echo '  <td>' . leaderboardFinderLink($row['finderName']) . '</td>';
                echo '  <td class="text-end">' . (int)$row['logCount'] . '</td>';
                echo '  <td class="text-end">' . (int)$row['foundItCount'] . '</td>';
                echo '  <td class="text-end">' . (int)$row['uniqueCaches'] . '</td>';
                echo '  <td>' . h(formatLogDate($row['lastSeenRaw'])) . '</td>';
                echo '</tr>';
                $rank++;
            }

            echo '        </tbody></table>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';

            echo '<div class="card mb-4">';
            echo '  <div class="card-body">';
            echo '    <h5 class="card-title">Run details</h5>';
            echo '    <ul class="mb-0">';
            echo '      <li>Total parsed log events (before dedup): ' . (int)$totalEvents . '</li>';
            echo '      <li>Unique logs by log ID: ' . count($seenLogs) . '</li>';
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
      <form action="gpxleaderboard.php" method="post" enctype="multipart/form-data" id="leaderboardForm" class="mx-auto" style="max-width: 900px;">
        <input type="file" name="leaderboardFiles[]" id="leaderboardFiles" class="d-none" accept=".gpx,.zip" multiple>
        <div id="leaderboardDropZone" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload GPX or zip files">
          <div class="h5 mb-2">Drop one or more GPX/ZIP files here</div>
          <div class="text-muted mb-0">or click to choose files</div>
          <div id="leaderboardSelected" class="small mt-3 text-dark"></div>
        </div>
        <div class="form-group mt-3 text-start">
          <label for="excludeFinder">Exclude finder name (optional)</label>
          <input type="text" class="form-control" id="excludeFinder" name="excludeFinder" placeholder="e.g., myusername">
          <div class="small text-muted mt-1">Case-insensitive exact match.</div>
        </div>
        <button id="leaderboardSubmit" type="submit" class="btn btn-primary mt-2" disabled>Build Leaderboard</button>
      </form>
    </div>
    <?php
}
?>

<script>
$(function () {
  var $form = $('#leaderboardForm');
  var $input = $('#leaderboardFiles');
  var $dropZone = $('#leaderboardDropZone');
  var $selected = $('#leaderboardSelected');
  var $submit = $('#leaderboardSubmit');

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

<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxleaderboard.php')); ?>