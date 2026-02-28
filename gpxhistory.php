<?php
require_once __DIR__ . '/includes/layout.php';

renderPageStart(array(
  'title' => 'Geocaching GPX History',
  'description' => 'Historical GPX activity heatmap',
  'activeNav' => 'gpxhistory',
));
?>
      <div class="headline">
        <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/gpx.png">
        <h1>Historical GPX activity heatmap</h1>
        <p class="lead">Drop multiple GPX/ZIP files at once. Pocket Queries are ordered by GPX &lt;time&gt; and visualized in a calendar heatmap.</p>
      </div>

<?php
$message = '';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';

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
            $logId = getLogIdFromNode($log);
            if ($logId === '') {
              continue;
            }

                $dateRaw = (string)$log->date;
                $day = substr($dateRaw, 0, 10);
                $type = (string)$log->type;
                $finder = (string)$log->finder;
                $text = trim(preg_replace('/\s+/', ' ', (string)$log->text));

                $entry = array(
                    'logId' => $logId,
                    'dateRaw' => $dateRaw,
                    'day' => $day,
                    'type' => $type,
                    'finder' => $finder,
                    'text' => $text,
                    'code' => $code,
                    'cacheName' => $cacheName,
                    'cacheUrl' => $cacheUrl,
                );
                $logs[] = $entry;
                $logIndex[$logId] = $entry;
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
                      if (!isset($prevLogIndex[$log['logId']])) {
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
            echo '<div class="d-flex flex-wrap align-items-center gap-2">';
            echo '<label for="heatmapYear" class="mb-0"><strong>Year</strong></label>';
            echo '<select id="heatmapYear" class="form-select form-select-sm w-auto">';
            foreach ($yearList as $year) {
              $selected = ($year === $defaultYear) ? ' selected' : '';
              echo '<option value="' . h($year) . '"' . $selected . '>' . h($year) . '</option>';
            }
            echo '</select>';
            echo '<span class="small text-muted">Color intensity = number of newly discovered logs on that day.</span>';
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

<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxhistory.php')); ?>
