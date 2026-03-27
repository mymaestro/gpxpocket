<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';
require_once __DIR__ . '/includes/county_lookup_helpers.php';

$extraHeadHtml = <<<'HTML'
  <script src="files/table2CSV.js"></script>
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
  >
  <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
  ></script>
  <style>
    #countyProgressMap {
      width: 100%;
      height: 520px;
      border-radius: 0.5rem;
      border: 1px solid rgba(0, 0, 0, 0.12);
    }
    .county-map-legend {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 1rem;
      font-size: 0.875rem;
    }
    .county-map-legend .swatch {
      display: inline-block;
      width: 0.9rem;
      height: 0.9rem;
      border-radius: 0.2rem;
      margin-right: 0.35rem;
      vertical-align: text-bottom;
      border: 1px solid rgba(0,0,0,0.2);
    }
    .county-processing-overlay {
      position: fixed;
      inset: 0;
      background: rgba(255, 255, 255, 0.92);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1080;
      padding: 1rem;
    }
    .county-processing-overlay.active {
      display: flex;
    }
    .county-processing-overlay .overlay-card {
      max-width: 520px;
      width: 100%;
    }
  </style>
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
    @ini_set('zlib.output_compression', '0');
    if (!headers_sent()) {
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-cache');
    }
    echo '<div class="alert alert-info alert-dismissible fade show" role="status">Processing upload. Large files can take a while; results will appear below when ready.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    echo str_repeat(' ', 4096);
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();

    $targetUsername = trim((string)(isset($_POST['targetUsername']) ? $_POST['targetUsername'] : ''));
    $uploads = normalizeUploadArray(isset($_FILES['countyFiles']) ? $_FILES['countyFiles'] : array());
    $maxUploadBytes = 100 * 1024 * 1024;

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
            $visitedFipsList = array_values(array_keys($countyFoundByFips));
            $foundCountsByFips = array();
            $maxFoundCount = 0;
            foreach ($countyFoundByFips as $foundCountyFips => $foundCountyInfo) {
              $foundCount = isset($foundCountyInfo['foundCount']) ? (int)$foundCountyInfo['foundCount'] : 0;
              $foundCountsByFips[$foundCountyFips] = $foundCount;
              if ($foundCount > $maxFoundCount) {
                $maxFoundCount = $foundCount;
              }
            }

            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Processed ' . count($allFindsByCode) . ' unique finds for ' . h($targetUsername) . '. Resolved ' . $resolvedFindCount . ' finds to county polygons.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
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

            if ($hasCountyDataset) {
              echo '<div class="card mb-4">';
              echo '  <div class="card-body">';
              echo '    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">';
                echo '      <h5 class="mb-0">Visited county map</h5>';
              echo '      <div class="county-map-legend">';
                echo '        <span><span class="swatch" style="background:#7fd3a0"></span>Visited (fewer finds)</span>';
                echo '        <span><span class="swatch" style="background:#0f5132"></span>Visited (more finds)</span>';
              echo '        <span><span class="swatch" style="background:#dee2e6"></span>Missing</span>';
              echo '      </div>';
              echo '    </div>';
              echo '    <div id="countyProgressMap" aria-label="County progress map"></div>';
              echo '  </div>';
              echo '</div>';

              echo '<script>';
              echo 'window.countyMapConfig = {';
              echo 'geojsonUrl: ' . json_encode('data/us-counties.geojson') . ',';
              echo 'visitedFips: ' . json_encode($visitedFipsList, JSON_UNESCAPED_SLASHES) . ',';
                echo 'foundCountsByFips: ' . json_encode($foundCountsByFips, JSON_UNESCAPED_SLASHES) . ',';
                echo 'maxFoundCount: ' . (int)$maxFoundCount . ',';
              echo 'foundCountyCount: ' . (int)$foundCountyCount . ',';
              echo 'missingCountyCount: ' . (int)$missingCountyCount . ',';
              echo 'totalCountyCount: ' . (int)$totalCountyCount;
              echo '};';
              echo '</script>';
            }

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

                echo '<tr data-status="' . strtolower($row['status']) . '" data-state="' . h($row['stateName']) . '" data-fips="' . h($row['fips']) . '">';
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
  <div id="countyProcessingOverlay" class="county-processing-overlay" aria-live="polite" aria-hidden="true">
    <div class="card shadow overlay-card">
      <div class="card-body text-center">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
        <div class="h6 mb-1">Processing county progress...</div>
        <div class="small text-muted">Large GPX files can take a while. Results will appear automatically.</div>
      </div>
    </div>
  </div>
  <script>
  $(function () {
    var $form = $('#countyForm');
    var $input = $('#countyFiles');
    var $dropZone = $('#countyDropZone');
    var $selected = $('#countySelected');
    var $submit = $('#countySubmit');
    var $processingOverlay = $('#countyProcessingOverlay');

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
        if ($processingOverlay.length) {
          $processingOverlay.addClass('active').attr('aria-hidden', 'false');
        }
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

    if (window.countyMapConfig && $('#countyProgressMap').length && typeof L !== 'undefined') {
      var mapConfig = window.countyMapConfig;
      var visitedFipsSet = {};
      var foundCountsByFips = mapConfig.foundCountsByFips || {};
      var maxFoundCount = Number(mapConfig.maxFoundCount || 0);
      (mapConfig.visitedFips || []).forEach(function (fips) {
        visitedFipsSet[String(fips)] = true;
      });

      function getFindCountForFips(fips) {
        var value = foundCountsByFips[String(fips)];
        var parsed = Number(value || 0);
        return Number.isFinite(parsed) ? parsed : 0;
      }

      function getVisitedFillColor(findCount) {
        if (maxFoundCount <= 1) {
          return '#198754';
        }

        var ratio = Math.max(0, Math.min(1, findCount / maxFoundCount));
        if (ratio <= 0.2) return '#b7e4c7';
        if (ratio <= 0.4) return '#95d5b2';
        if (ratio <= 0.6) return '#74c69d';
        if (ratio <= 0.8) return '#40916c';
        return '#1b4332';
      }

      var countyMap = L.map('countyProgressMap', {
        preferCanvas: true,
        zoomSnap: 0.5
      }).setView([37.8, -96], 4);

      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 9,
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
      }).addTo(countyMap);

      var stateNameByFips = {
        '01':'Alabama','02':'Alaska','04':'Arizona','05':'Arkansas','06':'California',
        '08':'Colorado','09':'Connecticut','10':'Delaware','11':'District of Columbia',
        '12':'Florida','13':'Georgia','15':'Hawaii','16':'Idaho','17':'Illinois',
        '18':'Indiana','19':'Iowa','20':'Kansas','21':'Kentucky','22':'Louisiana',
        '23':'Maine','24':'Maryland','25':'Massachusetts','26':'Michigan','27':'Minnesota',
        '28':'Mississippi','29':'Missouri','30':'Montana','31':'Nebraska','32':'Nevada',
        '33':'New Hampshire','34':'New Jersey','35':'New Mexico','36':'New York',
        '37':'North Carolina','38':'North Dakota','39':'Ohio','40':'Oklahoma','41':'Oregon',
        '42':'Pennsylvania','44':'Rhode Island','45':'South Carolina','46':'South Dakota',
        '47':'Tennessee','48':'Texas','49':'Utah','50':'Vermont','51':'Virginia',
        '53':'Washington','54':'West Virginia','55':'Wisconsin','56':'Wyoming',
        '60':'American Samoa','66':'Guam','69':'Northern Mariana Islands',
        '72':'Puerto Rico','78':'U.S. Virgin Islands'
      };

      function resolveStateName(feature, fips) {
        var props = feature.properties || {};
        var name = props.STATE_NAME || props.state_name || props.StateName || '';
        if (name) { return name; }
        var stateFips = fips.length >= 2 ? fips.substring(0, 2) : '';
        return stateNameByFips[stateFips] || stateFips;
      }

      function normalizeFips(feature) {
        if (!feature) {
          return '';
        }

        var featureId = feature.id != null ? String(feature.id) : '';
        if (/^\d{5}$/.test(featureId)) {
          return featureId;
        }

        var props = feature.properties || {};
        var candidates = [props.GEOID, props.geoid, props.FIPS, props.fips, props.id, props.ID];
        for (var i = 0; i < candidates.length; i++) {
          var digits = String(candidates[i] == null ? '' : candidates[i]).replace(/\D+/g, '');
          if (digits.length === 5) {
            return digits;
          }
        }

        var state = String(props.STATEFP || props.statefp || props.STATE || props.state || '').replace(/\D+/g, '');
        var county = String(props.COUNTYFP || props.countyfp || props.COUNTY || props.county || '').replace(/\D+/g, '');
        if (state.length && county.length) {
          return state.padStart(2, '0') + county.padStart(3, '0');
        }

        return '';
      }

      function countyStyle(feature) {
        var fips = normalizeFips(feature);
        var isVisited = !!visitedFipsSet[fips];
        var findCount = getFindCountForFips(fips);
        return {
          color: '#8c959d',
          weight: 0.35,
          opacity: 0.9,
          fillOpacity: isVisited ? 0.72 : 0.45,
          fillColor: isVisited ? getVisitedFillColor(findCount) : '#dee2e6'
        };
      }

      fetch(mapConfig.geojsonUrl)
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Map dataset request failed with status ' + response.status);
          }
          return response.json();
        })
        .then(function (geojsonData) {
          var layer = L.geoJSON(geojsonData, {
            style: countyStyle,
            onEachFeature: function (feature, featureLayer) {
              var props = feature.properties || {};
              var fips = normalizeFips(feature);
              var isVisited = !!visitedFipsSet[fips];
              var findCount = getFindCountForFips(fips);
              var countyName = props.NAME || props.name || '';
              var stateName = resolveStateName(feature, fips);
              var status = isVisited ? 'Visited' : 'Missing';
              var popupParts = [];
              popupParts.push('<strong>' + (countyName || 'County') + '</strong>');
              if (stateName) {
                popupParts.push('State: ' + stateName);
              }
              if (fips) {
                popupParts.push('FIPS: ' + fips);
              }
              popupParts.push('Status: ' + status);
              popupParts.push('Find count: ' + findCount);
              featureLayer.bindPopup(popupParts.join('<br>'));
            }
          }).addTo(countyMap);

          var bounds = layer.getBounds();
          if (bounds && bounds.isValid()) {
            countyMap.fitBounds(bounds.pad(0.01));
          }
        })
        .catch(function (error) {
          console.error(error);
          $('#countyProgressMap').html('<div class="p-3 text-danger">Unable to load county map geometry.</div>');
        });
    }
  });
  </script>
<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxcounty-progress.php')); ?>
