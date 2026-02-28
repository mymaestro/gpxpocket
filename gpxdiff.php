<?php
require_once __DIR__ . '/includes/layout.php';

renderPageStart(array(
  'title' => 'Geocaching GPX Diff',
  'description' => 'Diff two Geocaching GPX exports',
  'activeNav' => 'gpxdiff',
));
?>
      <div class="headline">
        <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/gpx.png">
        <h1>Compare two Geocaching GPX files</h1>
        <p class="lead">Upload two GPX (or ZIP containing GPX) files to see:<br>new caches, gone caches, and new logs since last time.</p>
      </div>

<?php
$message = '';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';

function loadUploadedGpx($fieldName, $maxUploadBytes, &$message) {
    if (!isset($_FILES[$fieldName])) {
        $message .= 'Missing file input: ' . $fieldName . '. ';
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $message .= 'Upload error for ' . $fieldName . '. ';
        return null;
    }

    $fileSource = $_FILES[$fieldName]['tmp_name'];
    $fileName = basename($_FILES[$fieldName]['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileBase = basename($fileName, '.' . pathinfo($fileName, PATHINFO_EXTENSION));
    $fileSize = (int)$_FILES[$fieldName]['size'];
    $fileZip = '';

    if (!is_uploaded_file($fileSource)) {
        $message .= 'Invalid upload source for ' . $fieldName . '. ';
        return null;
    }

    if ($fileSize <= 0 || $fileSize > $maxUploadBytes) {
        $message .= 'Invalid file size for ' . $fieldName . '. ';
        return null;
    }

    if ($fileExt !== 'gpx') {
        if ($fileExt !== 'zip') {
            $message .= 'Only GPX or ZIP allowed for ' . $fieldName . '. ';
            return null;
        }

        $filePath = pathinfo(realpath($fileSource), PATHINFO_DIRNAME);
        $zip = new ZipArchive;
        $foundGpx = false;

        if ($zip->open($fileSource) !== true) {
            $message .= 'Failed to open ZIP for ' . $fieldName . '. ';
            return null;
        }

        for ($f = 0; $f < $zip->numFiles; $f++) {
            $zfileName = $zip->getNameIndex($f);
            if ($zfileName === false || strpos($zfileName, "\0") !== false) {
                continue;
            }

            $zfileBase = basename($zfileName);
            if ($zfileBase !== $zfileName && strpos($zfileName, '/') !== false) {
                continue;
            }

            if (endswith(strtolower($zfileBase), '.gpx') && !endswith(strtolower($zfileBase), 'wpts.gpx')) {
                $zfileContents = $zip->getFromIndex($f);
                if ($zfileContents !== false) {
                    $tmpExtracted = tempnam($filePath, 'gpx_');
                    if ($tmpExtracted !== false && file_put_contents($tmpExtracted, $zfileContents) !== false) {
                        $fileZip = $fileSource;
                        $fileSource = $tmpExtracted;
                        $fileBase = pathinfo($zfileBase, PATHINFO_FILENAME);
                        $fileSize = filesize($fileSource);
                        $foundGpx = true;
                        break;
                    }
                }
            }
        }

        $zip->close();

        if (!$foundGpx) {
            $message .= 'No GPX found in ZIP for ' . $fieldName . '. ';
            return null;
        }
    }

    libxml_use_internal_errors(true);
    $xmlProbe = simplexml_load_file($fileSource, 'SimpleXMLElement', LIBXML_NONET);
    $isValid = ($xmlProbe !== false && strtolower($xmlProbe->getName()) === 'gpx');
    libxml_clear_errors();

    if (!$isValid) {
        $message .= 'Invalid GPX format for ' . $fieldName . '. ';
        return null;
    }

    return array(
        'source' => $fileSource,
        'zip' => $fileZip,
        'name' => $fileName,
        'base' => $fileBase,
        'size' => $fileSize,
    );
}

function parseGpxSnapshot($fileSource, $displayName) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($fileSource, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    if ($xml === false) {
        return null;
    }

  $gpxTimeRaw = trim((string)$xml->time);
  $gpxTimeTs = strtotime($gpxTimeRaw);
  if ($gpxTimeTs === false) {
    $gpxTimeTs = filemtime($fileSource);
    $gpxTimeRaw = gmdate('c', $gpxTimeTs);
  }

    $rows = array();
    foreach ($xml->wpt as $wpt) {
        $cacheCode = trim((string)$wpt->name);
        $cacheTypeParts = explode('|', (string)$wpt->type);
        $cacheType = $cacheTypeParts[0];
        if ($cacheType !== 'Geocache' || $cacheCode === '') {
            continue;
        }

        $cacheInfo = $wpt->children('http://www.groundspeak.com/cache/1/0/1');
        $cacheLat = (float)$wpt['lat'];
        $cacheLon = (float)$wpt['lon'];

        $rows[$cacheCode] = array(
            'code' => $cacheCode,
            'name' => (string)$wpt->urlname,
            'url' => (string)$wpt->url,
            'lat' => $cacheLat,
            'lon' => $cacheLon,
            'latlon' => DECtoDMS($cacheLat, $cacheLon),
            'container' => (string)$cacheInfo->cache->container,
            'difficulty' => (string)$cacheInfo->cache->difficulty,
            'terrain' => (string)$cacheInfo->cache->terrain,
          'logs' => array(),
          'logIndex' => array(),
        );

        if (isset($cacheInfo->cache->logs) && isset($cacheInfo->cache->logs->log)) {
          foreach ($cacheInfo->cache->logs->log as $log) {
            $logDate = (string)$log->date;
            $logType = (string)$log->type;
            $logFinder = (string)$log->finder;
            $logText = trim(preg_replace('/\s+/', ' ', (string)$log->text));
            $signature = sha1($logDate . '|' . $logType . '|' . $logFinder . '|' . $logText);

            $rows[$cacheCode]['logs'][] = array(
              'date' => $logDate,
              'type' => $logType,
              'finder' => $logFinder,
              'text' => $logText,
              'signature' => $signature,
            );
            $rows[$cacheCode]['logIndex'][$signature] = true;
          }
        }
    }

    ksort($rows);
  return array(
    'displayName' => $displayName,
    'gpxTimeRaw' => $gpxTimeRaw,
    'gpxTimeTs' => $gpxTimeTs,
    'rows' => $rows,
  );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxUploadBytes = 10 * 1024 * 1024;
    $left = loadUploadedGpx('fileLeft', $maxUploadBytes, $message);
    $right = loadUploadedGpx('fileRight', $maxUploadBytes, $message);

    if ($left && $right) {
      $leftSnapshot = parseGpxSnapshot($left['source'], $left['name']);
      $rightSnapshot = parseGpxSnapshot($right['source'], $right['name']);

      if ($leftSnapshot === null || $rightSnapshot === null) {
            $message .= 'Unable to parse one of the GPX files. ';
            echo '    <div class="alert alert-danger" role="alert">' . h($message) . '</div>';
        } else {
        $snapshots = array($leftSnapshot, $rightSnapshot);
        usort($snapshots, function ($a, $b) {
          return $a['gpxTimeTs'] <=> $b['gpxTimeTs'];
        });

        $older = $snapshots[0];
        $newer = $snapshots[1];
        $leftRows = $older['rows'];
        $rightRows = $newer['rows'];

            $leftCodes = array_keys($leftRows);
            $rightCodes = array_keys($rightRows);

            $addedCodes = array_values(array_diff($rightCodes, $leftCodes));
            $removedCodes = array_values(array_diff($leftCodes, $rightCodes));
            $commonCodes = array_values(array_intersect($leftCodes, $rightCodes));

            sort($addedCodes);
            sort($removedCodes);
            sort($commonCodes);

            $newLogRows = array();
            $newLogCount = 0;
            foreach ($commonCodes as $code) {
                $a = $leftRows[$code];
                $b = $rightRows[$code];
              $newLogs = array();
              foreach ($b['logs'] as $logEntry) {
                if (!isset($a['logIndex'][$logEntry['signature']])) {
                  $newLogs[] = $logEntry;
                }
              }

              if (!empty($newLogs)) {
                $newLogRows[] = array(
                        'code' => $code,
                        'url' => $b['url'] !== '' ? $b['url'] : $a['url'],
                  'name' => $b['name'] !== '' ? $b['name'] : $a['name'],
                  'logs' => $newLogs,
                    );
                $newLogCount += count($newLogs);
                }
            }

            echo '    <div class="row mb-4">';

            echo '      <div class="col-md-6 mb-3 mb-md-0">';
            echo '        <div class="card h-100 border-secondary">';
            echo '          <div class="card-header"><strong>Older Pocket Query</strong></div>';
            echo '          <div class="card-body">';
            echo '            <div><strong>File:</strong> ' . h($older['displayName']) . '</div>';
            echo '            <div><strong>Date:</strong> ' . h(formatSnapshotDate($older['gpxTimeTs'])) . '</div>';
            echo '            <hr>';
            echo '            <div><strong>Total caches:</strong> ' . count($leftCodes) . '</div>';
            echo '            <div><strong>Gone in newer:</strong> ' . count($removedCodes) . '</div>';
            echo '            <div><strong>Still existing:</strong> ' . count($commonCodes) . '</div>';
            echo '          </div>';
            echo '        </div>';
            echo '      </div>';

            echo '      <div class="col-md-6">';
            echo '        <div class="card h-100 border-success">';
            echo '          <div class="card-header"><strong>Newer Pocket Query</strong></div>';
            echo '          <div class="card-body">';
            echo '            <div><strong>File:</strong> ' . h($newer['displayName']) . '</div>';
            echo '            <div><strong>Date:</strong> ' . h(formatSnapshotDate($newer['gpxTimeTs'])) . '</div>';
            echo '            <hr>';
            echo '            <div><strong>Total caches:</strong> ' . count($rightCodes) . '</div>';
            echo '            <div><strong>New caches:</strong> ' . count($addedCodes) . '</div>';
            echo '            <div><strong>New logs:</strong> ' . $newLogCount . '</div>';
            echo '          </div>';
            echo '        </div>';
            echo '      </div>';

            echo '    </div>';

            if (!empty($addedCodes)) {
              echo '    <h3 class="mt-4">New caches (present in newer Pocket Query)</h3>';
                echo '    <table class="table table-striped"><thead><tr><th>Code</th><th>Name</th><th>Latitude/Longitude</th><th>Size</th><th>D/T</th></tr></thead><tbody>';
                foreach ($addedCodes as $code) {
                    $r = $rightRows[$code];
                    $url = filter_var($r['url'], FILTER_VALIDATE_URL) ? $r['url'] : '#';
                    echo '<tr>';
                    echo '<td><a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($r['code']) . '</a></td>';
                    echo '<td>' . h($r['name']) . '</td>';
                    echo '<td>' . h($r['latlon']) . '</td>';
                    echo '<td>' . h($r['container']) . '</td>';
                    echo '<td>' . h($r['difficulty']) . ' / ' . h($r['terrain']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            if (!empty($removedCodes)) {
              echo '    <h3 class="mt-4">Gone/archived caches (missing from newer Pocket Query)</h3>';
                echo '    <table class="table table-striped"><thead><tr><th>Code</th><th>Name</th><th>Latitude/Longitude</th><th>Size</th><th>D/T</th></tr></thead><tbody>';
                foreach ($removedCodes as $code) {
                    $r = $leftRows[$code];
                    $url = filter_var($r['url'], FILTER_VALIDATE_URL) ? $r['url'] : '#';
                    echo '<tr>';
                    echo '<td><a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($r['code']) . '</a></td>';
                    echo '<td>' . h($r['name']) . '</td>';
                    echo '<td>' . h($r['latlon']) . '</td>';
                    echo '<td>' . h($r['container']) . '</td>';
                    echo '<td>' . h($r['difficulty']) . ' / ' . h($r['terrain']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            if (!empty($newLogRows)) {
              echo '    <h3 class="mt-4">Still-existing caches with new logs</h3>';
              echo '    <table class="table table-striped"><thead><tr><th>Code</th><th>Name</th><th>New Logs</th></tr></thead><tbody>';
              foreach ($newLogRows as $row) {
                    $url = filter_var($row['url'], FILTER_VALIDATE_URL) ? $row['url'] : '#';
                    echo '<tr>';
                    echo '<td><a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($row['code']) . '</a></td>';
                echo '<td>' . h($row['name']) . '</td>';
                echo '<td>';
                foreach ($row['logs'] as $logEntry) {
                  echo '<div class="mb-2">'
                    . '<strong>' . h($logEntry['type']) . '</strong>'
                    . ' on ' . h(formatLogDate($logEntry['date']))
                    . ' by ' . h($logEntry['finder']);
                  if ($logEntry['text'] !== '') {
                    echo '<br>' . h($logEntry['text']);
                  }
                  echo '</div>';
                }
                echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            if (empty($addedCodes) && empty($removedCodes) && empty($newLogRows)) {
                echo '    <div class="alert alert-info" role="alert">No differences found.</div>';
            }
        }
    } else {
        echo '    <div class="alert alert-danger" role="alert">' . h($message) . '</div>';
    }

    cleanupExtracted($left);
    cleanupExtracted($right);
} else {
    echo '
    <div class="headline">
      <form action="gpxdiff.php" method="post" enctype="multipart/form-data" id="formDiff" class="mx-auto" style="max-width: 900px;">
        <div class="row">
          <div class="col-md-6 mb-3">
            <input type="file" name="fileLeft" id="fileLeft" class="d-none" accept=".gpx,.zip">
            <div id="dropZoneLeft" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload first GPX or zip file">
              <div class="h5 mb-2">File A</div>
              <div class="text-muted mb-0">Drop GPX/ZIP here or click</div>
              <div id="selectedLeft" class="small mt-3 text-dark"></div>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <input type="file" name="fileRight" id="fileRight" class="d-none" accept=".gpx,.zip">
            <div id="dropZoneRight" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload second GPX or zip file">
              <div class="h5 mb-2">File B</div>
              <div class="text-muted mb-0">Drop GPX/ZIP here or click</div>
              <div id="selectedRight" class="small mt-3 text-dark"></div>
            </div>
          </div>
        </div>
        <div class="small text-muted mb-2">Files are automatically ordered by GPX &lt;time&gt; for accurate older → newer comparison.</div>
        <button id="compareButton" type="submit" class="btn btn-primary mt-2" disabled>Compare Files</button>
      </form>
    </div>
    ';
}
?>
    </div>
  </main>

  <script>
  $(function () {
    function wireDropzone(inputSelector, zoneSelector, nameSelector) {
      var $input = $(inputSelector);
      var $zone = $(zoneSelector);
      var $name = $(nameSelector);

      if (!$input.length || !$zone.length) {
        return;
      }

      function showName(file) {
        if (file && file.name) {
          $name.text('Selected: ' + file.name);
        }
      }

      function updateCompareButton() {
        var hasLeft = $('#fileLeft')[0] && $('#fileLeft')[0].files.length > 0;
        var hasRight = $('#fileRight')[0] && $('#fileRight')[0].files.length > 0;
        $('#compareButton').prop('disabled', !(hasLeft && hasRight));
      }

      $zone.on('click', function () {
        $input.trigger('click');
      });

      $zone.on('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          $input.trigger('click');
        }
      });

      $input.on('change', function () {
        var file = this.files && this.files.length ? this.files[0] : null;
        showName(file);
        updateCompareButton();
      });

      $zone.on('dragenter dragover', function (event) {
        event.preventDefault();
        event.stopPropagation();
        $zone.addClass('dropzone-active border-primary');
      });

      $zone.on('dragleave dragend drop', function (event) {
        event.preventDefault();
        event.stopPropagation();
        $zone.removeClass('dropzone-active border-primary');
      });

      $zone.on('drop', function (event) {
        var files = event.originalEvent.dataTransfer ? event.originalEvent.dataTransfer.files : null;
        if (!files || !files.length) {
          return;
        }

        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(files[0]);
        $input[0].files = dataTransfer.files;
        showName(files[0]);
        updateCompareButton();
      });
    }

    wireDropzone('#fileLeft', '#dropZoneLeft', '#selectedLeft');
    wireDropzone('#fileRight', '#dropZoneRight', '#selectedRight');
  });
  </script>

<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxdiff.php')); ?>
