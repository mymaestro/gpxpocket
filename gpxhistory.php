<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Historical GPX activity heatmap">
  <meta name="author" content="Warren Gill">
  <title>Geocaching GPX History</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='0.9em' font-size='90'%3E%F0%9F%8C%90%3C/text%3E%3C/svg%3E">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="files/styles.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-light bg-light fixed-top border-bottom">
    <a class="navbar-brand" href="#"><i class="bi bi-globe-americas mr-2" aria-hidden="true"></i>Geocaching</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-top" aria-controls="navbar-top" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbar-top">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item">
          <a class="nav-link" href="./index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpx2csv.php">GPX to CSV</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpxdiff.php">GPX Diff</a>
        </li>
        <li class="nav-item active">
          <a class="nav-link" href="./gpxhistory.php">GPX History <span class="sr-only">(current)</span></a>
        </li>
      </ul>
    </div>
  </nav>

  <main role="main" class="flex-shrink-0">
    <div class="container-fluid px-3 px-md-4">
      <div class="headline">
        <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/gpx.png">
        <h1>Historical GPX activity heatmap</h1>
        <p class="lead">Drop multiple GPX/ZIP files at once. Pocket Queries are ordered by GPX &lt;time&gt; and visualized in a calendar heatmap.</p>
      </div>

<?php
$message = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function endswith($haystack, $needle) {
    $strlen = strlen($haystack);
    $testlen = strlen($needle);
    if ($testlen > $strlen) return false;
    return substr_compare($haystack, $needle, $strlen - $testlen, $testlen) === 0;
}

function normalizeUploadArray($fileField) {
    if (!isset($fileField['name'])) {
        return array();
    }

    if (!is_array($fileField['name'])) {
        return array($fileField);
    }

    $normalized = array();
    $count = count($fileField['name']);
    for ($index = 0; $index < $count; $index++) {
        $normalized[] = array(
            'name' => $fileField['name'][$index],
            'type' => $fileField['type'][$index],
            'tmp_name' => $fileField['tmp_name'][$index],
            'error' => $fileField['error'][$index],
            'size' => $fileField['size'][$index],
        );
    }
    return $normalized;
}

function extractGpxFromUpload($upload, $maxUploadBytes, &$message) {
    if (!isset($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $fileSource = $upload['tmp_name'];
    $fileName = basename($upload['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileSize = (int)$upload['size'];
    $fileZip = '';

    if (!is_uploaded_file($fileSource)) {
        $message .= 'Invalid upload source for ' . $fileName . '. ';
        return null;
    }

    if ($fileSize <= 0 || $fileSize > $maxUploadBytes) {
        $message .= 'Invalid file size for ' . $fileName . '. ';
        return null;
    }

    if ($fileExt !== 'gpx') {
        if ($fileExt !== 'zip') {
            $message .= 'Only GPX/ZIP supported: ' . $fileName . '. ';
            return null;
        }

        $filePath = pathinfo(realpath($fileSource), PATHINFO_DIRNAME);
        $zip = new ZipArchive;
        $foundGpx = false;

        if ($zip->open($fileSource) !== true) {
            $message .= 'Failed to open ZIP: ' . $fileName . '. ';
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
                        $foundGpx = true;
                        break;
                    }
                }
            }
        }
        $zip->close();

        if (!$foundGpx) {
            $message .= 'No GPX found inside ZIP: ' . $fileName . '. ';
            return null;
        }
    }

    return array(
        'source' => $fileSource,
        'zip' => $fileZip,
        'name' => $fileName,
    );
}

function parseSnapshot($gpxPath, $displayName) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($gpxPath, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();
    if ($xml === false || strtolower($xml->getName()) !== 'gpx') {
        return null;
    }

    $gpxName = trim((string)$xml->name);
    $gpxTimeRaw = trim((string)$xml->time);
    $gpxTimeTs = strtotime($gpxTimeRaw);
    if ($gpxTimeTs === false) {
        $gpxTimeTs = filemtime($gpxPath);
        $gpxTimeRaw = gmdate('c', $gpxTimeTs);
    }

    $caches = array();
    foreach ($xml->wpt as $wpt) {
        $code = trim((string)$wpt->name);
        $typeParts = explode('|', (string)$wpt->type);
        $cacheType = $typeParts[0];
        if ($cacheType !== 'Geocache' || $code === '') {
            continue;
        }

        $cacheInfo = $wpt->children('http://www.groundspeak.com/cache/1/0/1');
        $cacheName = (string)$wpt->urlname;
        $cacheUrl = (string)$wpt->url;
        $logs = array();
        $logIndex = array();

        if (isset($cacheInfo->cache->logs) && isset($cacheInfo->cache->logs->log)) {
            foreach ($cacheInfo->cache->logs->log as $log) {
                $dateRaw = (string)$log->date;
                $day = substr($dateRaw, 0, 10);
                $type = (string)$log->type;
                $finder = (string)$log->finder;
                $text = trim(preg_replace('/\s+/', ' ', (string)$log->text));
                $signature = sha1($dateRaw . '|' . $type . '|' . $finder . '|' . $text);

                $entry = array(
                    'dateRaw' => $dateRaw,
                    'day' => $day,
                    'type' => $type,
                    'finder' => $finder,
                    'text' => $text,
                    'signature' => $signature,
                    'code' => $code,
                    'cacheName' => $cacheName,
                    'cacheUrl' => $cacheUrl,
                );
                $logs[] = $entry;
                $logIndex[$signature] = $entry;
            }
        }

        $caches[$code] = array(
            'code' => $code,
            'name' => $cacheName,
            'url' => $cacheUrl,
            'logs' => $logs,
            'logIndex' => $logIndex,
        );
    }

    ksort($caches);

    return array(
        'displayName' => $displayName,
        'gpxName' => $gpxName,
        'gpxTimeRaw' => $gpxTimeRaw,
        'gpxTimeTs' => $gpxTimeTs,
        'caches' => $caches,
    );
}

function cleanupExtracted($parsedUpload) {
    if (!$parsedUpload) {
        return;
    }
    if (!empty($parsedUpload['zip']) && file_exists($parsedUpload['zip'])) {
        unlink($parsedUpload['zip']);
    }
    if (!empty($parsedUpload['source']) && file_exists($parsedUpload['source'])) {
        unlink($parsedUpload['source']);
    }
}

function formatDisplayDate($rawDate, $timestamp) {
  if (is_numeric($timestamp) && (int)$timestamp > 0) {
    return date('M j, Y g:i A T', (int)$timestamp);
  }

  $parsed = strtotime((string)$rawDate);
  if ($parsed !== false) {
    return date('M j, Y g:i A T', $parsed);
  }

  return (string)$rawDate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxUploadBytes = 10 * 1024 * 1024;
    $uploads = normalizeUploadArray($_FILES['historyFiles']);

    if (count($uploads) < 2) {
        echo '<div class="alert alert-danger" role="alert">Please upload at least 2 GPX/ZIP files.</div>';
    } else {
        $parsedUploads = array();
        $snapshots = array();

        foreach ($uploads as $upload) {
            $parsedUpload = extractGpxFromUpload($upload, $maxUploadBytes, $message);
            if (!$parsedUpload) {
                continue;
            }
            $parsedUploads[] = $parsedUpload;

            $snapshot = parseSnapshot($parsedUpload['source'], $parsedUpload['name']);
            if ($snapshot !== null) {
                $snapshots[] = $snapshot;
            } else {
                $message .= 'Could not parse GPX: ' . $parsedUpload['name'] . '. ';
            }
        }

        foreach ($parsedUploads as $parsedUpload) {
            cleanupExtracted($parsedUpload);
        }

        if (count($snapshots) < 2) {
            echo '<div class="alert alert-danger" role="alert">Need at least 2 valid GPX Pocket Queries. ' . h($message) . '</div>';
        } else {
            usort($snapshots, function ($a, $b) {
                return $a['gpxTimeTs'] <=> $b['gpxTimeTs'];
            });

            $dailyCounts = array();
            $dailyDetails = array();

            for ($i = 1; $i < count($snapshots); $i++) {
                $prev = $snapshots[$i - 1];
                $curr = $snapshots[$i];

                foreach ($curr['caches'] as $code => $currCache) {
                    $prevLogIndex = isset($prev['caches'][$code]) ? $prev['caches'][$code]['logIndex'] : array();

                    foreach ($currCache['logs'] as $log) {
                        if (!isset($prevLogIndex[$log['signature']])) {
                            $day = $log['day'];
                            if ($day === '' || strlen($day) < 10) {
                                continue;
                            }

                            if (!isset($dailyCounts[$day])) {
                                $dailyCounts[$day] = 0;
                            }
                            $dailyCounts[$day]++;

                            if (!isset($dailyDetails[$day])) {
                                $dailyDetails[$day] = array();
                            }
                            $dailyDetails[$day][] = array(
                                'code' => $log['code'],
                                'cacheName' => $log['cacheName'],
                                'cacheUrl' => $log['cacheUrl'],
                                'type' => $log['type'],
                                'finder' => $log['finder'],
                                'text' => $log['text'],
                                'snapshotName' => $curr['displayName'],
                                'snapshotTime' => $curr['gpxTimeRaw'],
                            );
                        }
                    }
                }
            }

            ksort($dailyCounts);
            ksort($dailyDetails);

            $years = array();
            foreach ($dailyCounts as $day => $count) {
                $years[(int)substr($day, 0, 4)] = true;
            }
            $yearList = array_keys($years);
            sort($yearList);
            $defaultYear = !empty($yearList) ? max($yearList) : (int)date('Y');

            echo '<div class="alert alert-success" role="alert">Processed ' . count($snapshots) . ' Pocket Queries. Found ' . array_sum($dailyCounts) . ' new logs across ' . count($dailyCounts) . ' days.</div>';

            echo '<div class="row history-results-row">';

            echo '<div class="col-lg-4 history-left-column">';
            echo '<div class="card mb-4"><div class="card-body">';
            echo '<h5 class="mb-3">Selected day details</h5>';
            echo '<div id="dayDetails" class="small text-muted">Click a colored day cell to view logs.</div>';
            echo '</div></div>';
            echo '</div>';

            echo '<div class="col-lg-8 history-right-column">';
            echo '<div class="card mb-4"><div class="card-body">';
            echo '<div class="form-inline">';
            echo '<label for="heatmapYear" class="mr-2"><strong>Year</strong></label>';
            echo '<select id="heatmapYear" class="form-control">';
            foreach ($yearList as $year) {
              $selected = ($year === $defaultYear) ? ' selected' : '';
              echo '<option value="' . h($year) . '"' . $selected . '>' . h($year) . '</option>';
            }
            echo '</select>';
            echo '<span class="ml-3 small text-muted">Color intensity = number of newly discovered logs on that day.</span>';
            echo '</div>';
            echo '<div id="calendarHeatmap" class="mt-4"></div>';
            echo '</div></div>';

            echo '<div class="card mb-4"><div class="card-body">';
            echo '<h5 class="mb-3">Pocket Queries (chronological)</h5>';
            echo '<ul class="mb-0">';
            foreach ($snapshots as $snapshot) {
              echo '<li>' . h(formatDisplayDate($snapshot['gpxTimeRaw'], $snapshot['gpxTimeTs'])) . ' — ' . h($snapshot['displayName']);
              if ($snapshot['gpxName'] !== '') {
                echo ' (' . h($snapshot['gpxName']) . ')';
              }
              echo '</li>';
            }
            echo '</ul>';
            echo '</div></div>';
            echo '</div>';

            echo '</div>';

            echo '<script>';
            echo 'window.heatmapCounts = ' . json_encode($dailyCounts, JSON_UNESCAPED_SLASHES) . ';';
            echo 'window.heatmapDetails = ' . json_encode($dailyDetails, JSON_UNESCAPED_SLASHES) . ';';
            echo 'window.heatmapDefaultYear = ' . json_encode($defaultYear) . ';';
            echo '</script>';
        }
    }
} else {
    echo '
    <div class="headline">
      <form action="gpxhistory.php" method="post" enctype="multipart/form-data" id="historyForm" class="mx-auto" style="max-width: 900px;">
        <input type="file" name="historyFiles[]" id="historyFiles" class="d-none" accept=".gpx,.zip" multiple>
        <div id="historyDropZone" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload GPX or zip files">
          <div class="h5 mb-2">Drop multiple GPX/ZIP files here</div>
          <div class="text-muted mb-0">or click to choose several files at once</div>
          <div id="historySelected" class="small mt-3 text-dark"></div>
        </div>
        <button id="historySubmit" type="submit" class="btn btn-primary mt-3" disabled>Build Heatmap</button>
      </form>
    </div>
    ';
}
?>
    </div>
  </main>

  <footer class="footer mt-auto py-3">
    <div class="container-fluid px-3 px-md-4">
      <span class="text-muted">Copyright 2026 FishParts Media. v1.0</span>
    </div>
  </footer>

  <button id="clearPageButton" type="button" class="btn btn-danger floating-action-button clear-page-button" aria-label="Start over" title="Start over">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
      <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
      <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1zm-3.5 0v-.5h-6V3z"/>
    </svg>
  </button>
  <button id="scrollTopButton" type="button" class="btn btn-primary floating-action-button scroll-top-button" aria-label="Scroll to top" title="Scroll to top">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
      <path fill-rule="evenodd" d="M7.646 4.646a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8 5.707 5.354 8.354a.5.5 0 1 1-.708-.708z"/>
    </svg>
  </button>

  <script>
  $(function () {
    var $form = $('#historyForm');
    var $input = $('#historyFiles');
    var $dropZone = $('#historyDropZone');
    var $selected = $('#historySelected');
    var $submit = $('#historySubmit');

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
      $submit.prop('disabled', files.length < 2);
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

  <script>
  $(function () {
    if (!window.heatmapCounts) {
      return;
    }

    var counts = window.heatmapCounts;
    var details = window.heatmapDetails || {};
    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function levelClass(value, maxValue) {
      if (!value || maxValue <= 0) return 'hm-level-0';
      var ratio = value / maxValue;
      if (ratio <= 0.2) return 'hm-level-1';
      if (ratio <= 0.4) return 'hm-level-2';
      if (ratio <= 0.65) return 'hm-level-3';
      return 'hm-level-4';
    }

    function renderDayDetails(dayKey) {
      var $panel = $('#dayDetails');
      var entries = details[dayKey] || [];
      if (!entries.length) {
        $panel.text('No new logs for ' + dayKey + '.');
        return;
      }

      var html = '<div><strong>' + dayKey + '</strong> (' + entries.length + ' new logs)</div><ul class="mt-2 mb-0">';
      for (var i = 0; i < entries.length; i++) {
        var entry = entries[i];
        var text = entry.text ? (': ' + entry.text) : '';
        var link = entry.cacheUrl ? '<a href="' + entry.cacheUrl + '" target="_blank" rel="noopener noreferrer">' + entry.code + '</a>' : entry.code;
        html += '<li>' + link + ' — ' + entry.type + ' by ' + entry.finder + text + '</li>';
      }
      html += '</ul>';
      $panel.html(html);
    }

    function renderCalendar(year) {
      var $root = $('#calendarHeatmap');
      $root.empty();

      var filteredKeys = Object.keys(counts).filter(function (key) {
        return key.indexOf(year + '-') === 0;
      });

      var maxValue = 0;
      for (var i = 0; i < filteredKeys.length; i++) {
        maxValue = Math.max(maxValue, counts[filteredKeys[i]]);
      }

      for (var month = 0; month < 12; month++) {
        var first = new Date(Date.UTC(year, month, 1));
        var last = new Date(Date.UTC(year, month + 1, 0));
        var firstWeekday = (first.getUTCDay() + 6) % 7;
        var daysInMonth = last.getUTCDate();

        var $card = $('<div class="card mb-3"></div>');
        var $body = $('<div class="card-body p-2"></div>');
        var $title = $('<div class="small font-weight-bold mb-2"></div>').text(monthNames[month] + ' ' + year);
        var $table = $('<table class="table table-sm table-bordered mb-0 heatmap-table"></table>');

        var weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        var $thead = $('<thead><tr></tr></thead>');
        for (var wd = 0; wd < weekdays.length; wd++) {
          $thead.find('tr').append($('<th class="text-center p-1 small"></th>').text(weekdays[wd]));
        }
        $table.append($thead);

        var $tbody = $('<tbody></tbody>');
        var day = 1;
        var rowCount = Math.ceil((firstWeekday + daysInMonth) / 7);

        for (var row = 0; row < rowCount; row++) {
          var $tr = $('<tr></tr>');
          for (var col = 0; col < 7; col++) {
            var cellIndex = row * 7 + col;
            if (cellIndex < firstWeekday || day > daysInMonth) {
              $tr.append('<td class="p-1 hm-empty"></td>');
            } else {
              var dd = String(day).padStart(2, '0');
              var mm = String(month + 1).padStart(2, '0');
              var key = year + '-' + mm + '-' + dd;
              var value = counts[key] || 0;
              var cls = levelClass(value, maxValue);
              var $td = $('<td class="p-1 text-center heatmap-cell"></td>');
              $td.addClass(cls).attr('data-day', key).attr('title', key + ': ' + value + ' new logs');
              $td.text(day);
              if (value > 0) {
                $td.addClass('is-clickable');
              }
              $tr.append($td);
              day++;
            }
          }
          $tbody.append($tr);
        }

        $table.append($tbody);
        $body.append($title, $table);
        $card.append($body);
        $root.append($card);
      }
    }

    $('#heatmapYear').on('change', function () {
      var year = parseInt($(this).val(), 10);
      renderCalendar(year);
      $('#dayDetails').text('Click a colored day cell to view logs.');
    });

    $('#calendarHeatmap').on('click', '.heatmap-cell.is-clickable', function () {
      var dayKey = $(this).attr('data-day');
      renderDayDetails(dayKey);
    });

    renderCalendar(parseInt(window.heatmapDefaultYear, 10));
  });
  </script>

  <script>
  $(function () {
    var $clearPageButton = $('#clearPageButton');
    var $scrollTopButton = $('#scrollTopButton');
    if (!$scrollTopButton.length || !$clearPageButton.length) {
      return;
    }

    function updateFloatingButtonVisibility() {
      if ($(window).scrollTop() > 220) {
        $scrollTopButton.addClass('is-visible');
        $clearPageButton.addClass('is-visible');
      } else {
        $scrollTopButton.removeClass('is-visible');
        $clearPageButton.removeClass('is-visible');
      }
    }

    $(window).on('scroll', updateFloatingButtonVisibility);
    updateFloatingButtonVisibility();

    $clearPageButton.on('click', function () {
      window.location.href = 'gpxhistory.php';
    });

    $scrollTopButton.on('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
