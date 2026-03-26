<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';
require_once __DIR__ . '/includes/county_lookup_helpers.php';

$extraHeadHtml = <<<'HTML'
  <script src="files/table2CSV.js"></script>
HTML;

renderPageStart(array(
  'title' => 'Geocaching County Progress',
  'description' => 'Track county progress from your My Finds Pocket Query',
  'activeNav' => 'gpxcountyprogress',
  'extraHeadHtml' => $extraHeadHtml,
));

function extractCountyFinderName($log) {
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

function isCountyFoundLogType($logType) {
    return strtolower(trim((string)$logType)) === 'found it';
}

function parseCountyFindsFromGpx($gpxPath, $displayName, $targetUsername, &$message) {
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

        $bestFindTs = null;
        $bestFindRaw = '';

        foreach ($cacheInfo->cache->logs->log as $log) {
            $logType = trim((string)$log->type);
            if (!isCountyFoundLogType($logType)) {
                continue;
            }

            $finderName = extractCountyFinderName($log);
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
            'firstFoundTs' => $bestFindTs,
            'firstFoundRaw' => $bestFindRaw,
            'sourceName' => $displayName,
        );
    }

    return $findsByCode;
}
?>
      <div class="headline">
        <h1>County progress from My Finds GPX</h1>
        <p class="lead">Upload one or more My Finds GPX/ZIP files to calculate US county coverage.</p>
      </div>
<?php
$message = '';
$geojsonMessage = '';
$geojsonPath = __DIR__ . '/data/us-counties.geojson';
$countyFeatures = loadCountyGeojsonFeatures($geojsonPath, $geojsonMessage);
$hasCountyDataset = count($countyFeatures) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUsername = trim((string)(isset($_POST['targetUsername']) ? $_POST['targetUsername'] : ''));
    $uploads = normalizeUploadArray(isset($_FILES['countyFiles']) ? $_FILES['countyFiles'] : array());
    $maxUploadBytes = 10 * 1024 * 1024;

    if ($targetUsername === '') {
        echo '<div class="alert alert-danger" role="alert">Enter your geocaching username for strict Found it matching.</div>';
    } elseif (count($uploads) < 1) {
        echo '<div class="alert alert-danger" role="alert">Please upload at least 1 GPX/ZIP file.</div>';
    } else {
        $parsedUploads = array();
        $allFindsByCode = array();

        foreach ($uploads as $upload) {
            $parsedUpload = extractGpxFromUpload($upload, $maxUploadBytes, $message);
            if (!$parsedUpload) {
                continue;
            }

            $parsedUploads[] = $parsedUpload;
            $fileFinds = parseCountyFindsFromGpx($parsedUpload['source'], $parsedUpload['name'], $targetUsername, $message);

            foreach ($fileFinds as $cacheCode => $find) {
                if (!isset($allFindsByCode[$cacheCode]) || $find['firstFoundTs'] < $allFindsByCode[$cacheCode]['firstFoundTs']) {
                    $allFindsByCode[$cacheCode] = $find;
                }
            }
        }

        foreach ($parsedUploads as $parsedUpload) {
            cleanupExtracted($parsedUpload);
        }

        if (count($allFindsByCode) < 1) {
            echo '<div class="alert alert-warning" role="alert">No matching Found it logs were found for ' . h($targetUsername) . '. ' . h($message) . '</div>';
        } else {
            $countyFoundByFips = array();
            $resolvedFindCount = 0;

            if ($hasCountyDataset) {
                foreach ($allFindsByCode as $find) {
                    $county = findCountyByPoint($find['lat'], $find['lon'], $countyFeatures);
                    if ($county === null) {
                        continue;
                    }

                    $fips = $county['fips'];
                    $resolvedFindCount++;

                    if (!isset($countyFoundByFips[$fips])) {
                        $countyFoundByFips[$fips] = array(
                            'fips' => $fips,
                            'stateName' => $county['stateName'],
                            'countyName' => $county['countyName'],
                            'foundCount' => 0,
                            'firstFoundTs' => $find['firstFoundTs'],
                            'firstFoundRaw' => $find['firstFoundRaw'],
                            'sampleCode' => $find['cacheCode'],
                            'sampleName' => $find['cacheName'],
                            'sampleUrl' => $find['cacheUrl'],
                        );
                    }

                    $countyFoundByFips[$fips]['foundCount']++;

                    if ($find['firstFoundTs'] < $countyFoundByFips[$fips]['firstFoundTs']) {
                        $countyFoundByFips[$fips]['firstFoundTs'] = $find['firstFoundTs'];
                        $countyFoundByFips[$fips]['firstFoundRaw'] = $find['firstFoundRaw'];
                        $countyFoundByFips[$fips]['sampleCode'] = $find['cacheCode'];
                        $countyFoundByFips[$fips]['sampleName'] = $find['cacheName'];
                        $countyFoundByFips[$fips]['sampleUrl'] = $find['cacheUrl'];
                    }
                }
            }

            $allCountiesByFips = array();
            foreach ($countyFeatures as $feature) {
                $allCountiesByFips[$feature['fips']] = array(
                    'fips' => $feature['fips'],
                    'stateName' => $feature['stateName'],
                    'countyName' => $feature['countyName'],
                );
            }

            $tableRows = array();
            foreach ($allCountiesByFips as $fips => $countyMeta) {
                $foundEntry = isset($countyFoundByFips[$fips]) ? $countyFoundByFips[$fips] : null;
                $tableRows[] = array(
                    'fips' => $fips,
                    'stateName' => $countyMeta['stateName'],
                    'countyName' => $countyMeta['countyName'],
                    'status' => $foundEntry ? 'Found' : 'Missing',
                    'firstFoundRaw' => $foundEntry ? $foundEntry['firstFoundRaw'] : '',
                    'firstFoundTs' => $foundEntry ? $foundEntry['firstFoundTs'] : 0,
                    'foundCount' => $foundEntry ? $foundEntry['foundCount'] : 0,
                    'sampleCode' => $foundEntry ? $foundEntry['sampleCode'] : '',
                    'sampleName' => $foundEntry ? $foundEntry['sampleName'] : '',
                    'sampleUrl' => $foundEntry ? $foundEntry['sampleUrl'] : '',
                );
            }

            usort($tableRows, function ($a, $b) {
                $stateCmp = strcasecmp($a['stateName'], $b['stateName']);
                if ($stateCmp !== 0) {
                    return $stateCmp;
                }

                return strcasecmp($a['countyName'], $b['countyName']);
            });

              $stateOptions = array();
              foreach ($tableRows as $row) {
                $stateName = trim((string)$row['stateName']);
                if ($stateName === '') {
                  continue;
                }
                $stateOptions[$stateName] = true;
              }
              $stateOptions = array_keys($stateOptions);
              natcasesort($stateOptions);

            $foundCountyCount = count($countyFoundByFips);
            $totalCountyCount = count($allCountiesByFips);
            $missingCountyCount = max(0, $totalCountyCount - $foundCountyCount);
            $coveragePct = $totalCountyCount > 0 ? ($foundCountyCount * 100 / $totalCountyCount) : 0;

            echo '<div class="alert alert-success" role="alert">Processed ' . count($allFindsByCode) . ' unique finds for ' . h($targetUsername) . '. Resolved ' . $resolvedFindCount . ' finds to county polygons.</div>';
            if ($message !== '') {
                echo '<div class="alert alert-warning" role="alert">' . h($message) . '</div>';
            }

            if (!$hasCountyDataset) {
                echo '<div class="alert alert-warning" role="alert">County dataset not available. Add a GeoJSON file at <code>data/us-counties.geojson</code> to enable county matching.</div>';
            }

            echo '<div class="row g-3 mb-4">';
            echo '  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Counties found</div><div class="display-6">' . (int)$foundCountyCount . '</div></div></div></div>';
            echo '  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Counties missing</div><div class="display-6">' . (int)$missingCountyCount . '</div></div></div></div>';
            echo '  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Coverage</div><div class="display-6">' . h(number_format($coveragePct, 1)) . '%</div><div class="small text-muted">' . (int)$foundCountyCount . ' / ' . (int)$totalCountyCount . '</div></div></div></div>';
            echo '</div>';

            echo '<div class="d-flex flex-wrap align-items-center gap-3 mb-3">';
            echo '  <div class="form-check">';
            echo '    <input class="form-check-input" type="checkbox" id="showMissingToggle" checked>';
            echo '    <label class="form-check-label" for="showMissingToggle">Show missing counties</label>';
            echo '  </div>';
            echo '  <div class="form-check">';
            echo '    <input class="form-check-input" type="checkbox" id="showFoundToggle" checked>';
            echo '    <label class="form-check-label" for="showFoundToggle">Show found counties</label>';
            echo '  </div>';
            echo '  <label for="stateFilter" class="mb-0 small text-muted">State</label>';
            echo '  <select id="stateFilter" class="form-select form-select-sm w-auto">';
            echo '    <option value="">All states</option>';
            foreach ($stateOptions as $stateOption) {
              echo '    <option value="' . h($stateOption) . '">' . h($stateOption) . '</option>';
            }
            echo '  </select>';
            echo '  <div class="small text-muted" id="countyFilterSummary"></div>';
            echo '  <div class="ms-auto">';
            echo '    <button type="button" id="exportCountyCsv" class="btn btn-outline-primary btn-sm">Export CSV</button>';
            echo '  </div>';
            echo '</div>';

            echo '<div class="table-responsive">';
            echo '<table id="countyProgressTable.csv" class="table table-striped table-sm align-middle">';
            echo '<thead><tr>';
            echo '<th>Status</th><th>State</th><th>County</th><th>FIPS</th><th>First found</th><th>Find count</th><th>Sample cache</th>';
            echo '</tr></thead><tbody>';

            foreach ($tableRows as $row) {
                $isFound = $row['status'] === 'Found';
                $firstFoundLabel = $isFound ? formatDisplayDate($row['firstFoundRaw'], $row['firstFoundTs']) : '—';
                $sampleCell = '—';

                if ($isFound && $row['sampleCode'] !== '') {
                    if ($row['sampleUrl'] !== '' && filter_var($row['sampleUrl'], FILTER_VALIDATE_URL)) {
                        $sampleCell = '<a href="' . h($row['sampleUrl']) . '" target="_blank" rel="noopener">' . h($row['sampleCode']) . '</a>';
                    } else {
                        $sampleCell = h($row['sampleCode']);
                    }

                    if ($row['sampleName'] !== '') {
                        $sampleCell .= '<div class="small text-muted">' . h($row['sampleName']) . '</div>';
                    }
                }

                echo '<tr data-status="' . strtolower($row['status']) . '" data-state="' . h($row['stateName']) . '">';
                echo '<td>' . h($row['status']) . '</td>';
                echo '<td>' . h($row['stateName']) . '</td>';
                echo '<td>' . h($row['countyName']) . '</td>';
                echo '<td>' . h($row['fips']) . '</td>';
                echo '<td>' . h($firstFoundLabel) . '</td>';
                echo '<td>' . (int)$row['foundCount'] . '</td>';
                echo '<td>' . $sampleCell . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }
    }
} else {
    if (!$hasCountyDataset) {
        echo '<div class="alert alert-warning" role="alert">County dataset not available yet. Add a GeoJSON file at <code>data/us-counties.geojson</code> before running county lookup.</div>';
    }

    echo '
    <div class="headline">
      <form action="gpxcounty-progress.php" method="post" enctype="multipart/form-data" id="countyForm" class="mx-auto" style="max-width: 960px;">
        <div class="mb-3 text-start">
          <label for="targetUsername" class="form-label"><strong>Geocaching username</strong></label>
          <input type="text" class="form-control" id="targetUsername" name="targetUsername" placeholder="Required for strict Found it matching" required>
        </div>
        <input type="file" name="countyFiles[]" id="countyFiles" class="d-none" accept=".gpx,.zip" multiple>
        <div id="countyDropZone" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload GPX or zip files">
          <div class="h5 mb-2">Drop one or more GPX/ZIP files here</div>
          <div class="text-muted mb-0">or click to choose files</div>
          <div id="countySelected" class="small mt-3 text-dark"></div>
        </div>
        <button id="countySubmit" type="submit" class="btn btn-primary mt-3" disabled>Build County Progress</button>
      </form>
    </div>
    ';
}
?>
  <script>
  $(function () {
    var $form = $('#countyForm');
    var $input = $('#countyFiles');
    var $dropZone = $('#countyDropZone');
    var $selected = $('#countySelected');
    var $submit = $('#countySubmit');

    if ($form.length && $input.length && $dropZone.length) {
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
    }

    var $table = $('#countyProgressTable\\.csv');
    var $showMissing = $('#showMissingToggle');
    var $showFound = $('#showFoundToggle');
    var $stateFilter = $('#stateFilter');
    var $summary = $('#countyFilterSummary');

    if ($table.length) {
      function applyCountyFilters() {
        var includeMissing = !$showMissing.length || $showMissing.is(':checked');
        var includeFound = !$showFound.length || $showFound.is(':checked');
        var selectedState = $stateFilter.length ? ($stateFilter.val() || '') : '';
        var visibleCount = 0;
        var totalCount = 0;

        $table.find('tbody tr').each(function () {
          totalCount++;
          var $row = $(this);
          var status = ($row.attr('data-status') || '').toLowerCase();
          var state = $row.attr('data-state') || '';

          var statusPass = (status === 'missing' && includeMissing) || (status === 'found' && includeFound);
          var statePass = !selectedState || state === selectedState;
          var show = statusPass && statePass;

          if (show) {
            visibleCount++;
            $row.show();
          } else {
            $row.hide();
          }
        });

        if ($summary.length) {
          $summary.text(visibleCount + ' of ' + totalCount + ' counties shown');
        }
      }

      $showMissing.on('change', applyCountyFilters);
      $showFound.on('change', applyCountyFilters);
      $stateFilter.on('change', applyCountyFilters);
      applyCountyFilters();
    }

    var $exportButton = $('#exportCountyCsv');
    if ($table.length && $exportButton.length && typeof $table.table2CSV === 'function') {
      $exportButton.on('click', function () {
        $table.table2CSV({
          delivery: 'download',
          filename: 'county-progress.csv',
          separator: ','
        });
      });
    }
  });
  </script>
<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxcounty-progress.php')); ?>
