<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';
require_once __DIR__ . '/includes/finds_parser_helpers.php';
require_once __DIR__ . '/includes/county_lookup_helpers.php';
require_once __DIR__ . '/includes/delorme_lookup_helpers.php';

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
    #deLormeProgressMap {
      width: 100%;
      height: 520px;
      border-radius: 0.5rem;
      border: 1px solid rgba(0, 0, 0, 0.12);
    }
    .delorme-map-legend {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 1rem;
      font-size: 0.875rem;
    }
    .delorme-map-legend .swatch {
      display: inline-block;
      width: 0.9rem;
      height: 0.9rem;
      border-radius: 0.2rem;
      margin-right: 0.35rem;
      vertical-align: text-bottom;
      border: 1px solid rgba(0,0,0,0.2);
    }
    .delorme-processing-overlay {
      position: fixed;
      inset: 0;
      background: rgba(255, 255, 255, 0.92);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1080;
      padding: 1rem;
    }
    .delorme-processing-overlay.active {
      display: flex;
    }
    .delorme-processing-overlay .overlay-card {
      max-width: 520px;
      width: 100%;
    }
  </style>
HTML;

renderPageStart(array(
  'title' => 'DeLorme Atlas Page Progress',
  'description' => 'Track DeLorme Atlas & Gazetteer page progress from your My Finds Pocket Query',
  'activeNav' => 'gpxdelormprogress',
  'extraHeadHtml' => $extraHeadHtml,
));
?>
      <div class="headline">
        <h1>DeLorme Atlas page progress from My Finds GPX</h1>
        <p class="lead">Upload one or more My Finds GPX/ZIP files to calculate DeLorme Atlas &amp; Gazetteer page coverage.</p>
      </div>
<?php
$debugEnabled = !empty($_GET['debug']) || !empty($_POST['debug']);
$requestStartedAt = microtime(true);
$debugStats = array();

$message = '';
$deLormeMessage = '';
$deLormePath = __DIR__ . '/data/delorme-pages.json';
$deLormeDataDir = __DIR__ . '/data/delorme';
$deLormePages = loadDeLormePages($deLormePath, $deLormeMessage);
$hasDeLormeDataset = count($deLormePages) > 0;

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
    $uploads = normalizeUploadArray(isset($_FILES['deLormeFiles']) ? $_FILES['deLormeFiles'] : array());
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
          $fileFinds = parseFoundCachesFromGpx($parsedUpload['source'], $parsedUpload['name'], $targetUsername, $message);

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
          $debugStats['unique_finds'] = count($allFindsByCode);
            $pagesFoundById = array();
            $resolvedFindCount = 0;

            if ($hasDeLormeDataset) {
                $matchedPages = matchDeLormePagesToFinds($allFindsByCode, $deLormePages, $deLormeDataDir);

                // matchDeLormePagesToFinds returns cacheCode => [ page, ... ] so that
                // finds in overlapping-edition states (e.g. Arkansas / Arkansas 2) are
                // credited to every matching atlas edition.
                foreach ($matchedPages as $cacheCode => $pages) {
                    $find = $allFindsByCode[$cacheCode];
                    $resolvedFindCount++;

                    foreach ($pages as $page) {
                        $pageId = $page['id'];

                        if (!isset($pagesFoundById[$pageId])) {
                            $pagesFoundById[$pageId] = array(
                                'id' => $pageId,
                                'stateName' => $page['stateName'],
                                'bookName' => $page['bookName'],
                                'page' => $page['page'],
                                'foundCount' => 0,
                                'firstFoundTs' => $find['firstFoundTs'],
                                'firstFoundRaw' => $find['firstFoundRaw'],
                                'sampleCode' => $find['cacheCode'],
                                'sampleName' => $find['cacheName'],
                                'sampleUrl' => $find['cacheUrl'],
                            );
                        }

                        $pagesFoundById[$pageId]['foundCount']++;

                        if ($find['firstFoundTs'] < $pagesFoundById[$pageId]['firstFoundTs']) {
                            $pagesFoundById[$pageId]['firstFoundTs'] = $find['firstFoundTs'];
                            $pagesFoundById[$pageId]['firstFoundRaw'] = $find['firstFoundRaw'];
                            $pagesFoundById[$pageId]['sampleCode'] = $find['cacheCode'];
                            $pagesFoundById[$pageId]['sampleName'] = $find['cacheName'];
                            $pagesFoundById[$pageId]['sampleUrl'] = $find['cacheUrl'];
                        }
                    }
                }
            }

            $allPagesById = array();
            foreach ($deLormePages as $page) {
                $allPagesById[$page['id']] = array(
                    'id' => $page['id'],
                    'stateName' => $page['stateName'],
                    'bookName' => $page['bookName'],
                    'page' => $page['page'],
                );
            }

            $tableRows = array();
            foreach ($allPagesById as $pageId => $pageMeta) {
                $foundEntry = isset($pagesFoundById[$pageId]) ? $pagesFoundById[$pageId] : null;
                $tableRows[] = array(
                    'id' => $pageId,
                    'stateName' => $pageMeta['stateName'],
                    'bookName' => $pageMeta['bookName'],
                    'page' => $pageMeta['page'],
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

                $bookCmp = strcasecmp($a['bookName'], $b['bookName']);
                if ($bookCmp !== 0) {
                    return $bookCmp;
                }

                return strcasecmp($a['page'], $b['page']);
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

            $foundPageCount = count($pagesFoundById);
            $totalPageCount = count($allPagesById);
            $missingPageCount = max(0, $totalPageCount - $foundPageCount);
            $coveragePct = $totalPageCount > 0 ? ($foundPageCount * 100 / $totalPageCount) : 0;
            $debugStats['resolved_finds'] = $resolvedFindCount;
            $debugStats['matched_pages'] = $foundPageCount;
            $debugStats['total_pages'] = $totalPageCount;
            $visitedIdsList = array_values(array_keys($pagesFoundById));
            $foundCountsByPageId = array();
            $maxFoundCount = 0;
            foreach ($pagesFoundById as $foundPageId => $foundPageInfo) {
              $foundCount = isset($foundPageInfo['foundCount']) ? (int)$foundPageInfo['foundCount'] : 0;
              $foundCountsByPageId[$foundPageId] = $foundCount;
              if ($foundCount > $maxFoundCount) {
                $maxFoundCount = $foundCount;
              }
            }

            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">Processed ' . count($allFindsByCode) . ' unique finds for ' . h($targetUsername) . '. Resolved ' . $resolvedFindCount . ' finds to DeLorme pages.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            if ($message !== '') {
                echo '<div class="alert alert-warning" role="alert">' . h($message) . '</div>';
            }
            if (!$hasDeLormeDataset) {
                echo '<div class="alert alert-warning" role="alert">DeLorme dataset not available. Add <code>data/delorme-pages.json</code> to enable page matching.</div>';
            }

            echo '<div class="row g-3 mb-4">';
            echo '  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Pages found</div><div class="display-6">' . (int)$foundPageCount . '</div></div></div></div>';
            echo '  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Pages missing</div><div class="display-6">' . (int)$missingPageCount . '</div></div></div></div>';
            echo '  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Coverage</div><div class="display-6">' . h(number_format($coveragePct, 1)) . '%</div><div class="small text-muted">' . (int)$foundPageCount . ' / ' . (int)$totalPageCount . '</div></div></div></div>';
            echo '</div>';

            if ($hasDeLormeDataset) {
              echo '<div class="card mb-4">';
              echo '  <div class="card-body">';
              echo '    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">';
              echo '      <h5 class="mb-0">Visited pages map</h5>';
              echo '      <div class="delorme-map-legend">';
              echo '        <span><span class="swatch" style="background:#7fd3a0"></span>Visited (fewer finds)</span>';
              echo '        <span><span class="swatch" style="background:#0f5132"></span>Visited (more finds)</span>';
              echo '        <span><span class="swatch" style="background:#dee2e6"></span>Missing</span>';
              echo '      </div>';
              echo '    </div>';
              echo '    <div id="deLormeProgressMap" aria-label="DeLorme page progress map"></div>';
              echo '  </div>';
              echo '</div>';

              echo '<script>';
              echo 'window.deLormeMapConfig = {';
              echo 'indexUrl: ' . json_encode('data/delorme-pages.json') . ',';
              echo 'visitedIds: ' . json_encode($visitedIdsList, JSON_UNESCAPED_SLASHES) . ',';
              echo 'foundCountsByPageId: ' . json_encode($foundCountsByPageId, JSON_UNESCAPED_SLASHES) . ',';
              echo 'maxFoundCount: ' . (int)$maxFoundCount;
              echo '};';
              echo '</script>';
            }

            echo '<div class="d-flex flex-wrap align-items-center gap-3 mb-3">';
            echo '  <div class="form-check">';
            echo '    <input class="form-check-input" type="checkbox" id="showMissingToggle" checked>';
            echo '    <label class="form-check-label" for="showMissingToggle">Show missing pages</label>';
            echo '  </div>';
            echo '  <div class="form-check">';
            echo '    <input class="form-check-input" type="checkbox" id="showFoundToggle" checked>';
            echo '    <label class="form-check-label" for="showFoundToggle">Show found pages</label>';
            echo '  </div>';
            echo '  <label for="stateFilter" class="mb-0 small text-muted">State</label>';
            echo '  <select id="stateFilter" class="form-select form-select-sm w-auto">';
            echo '    <option value="">All states</option>';
            foreach ($stateOptions as $stateOption) {
                echo '    <option value="' . h($stateOption) . '">' . h($stateOption) . '</option>';
            }
            echo '  </select>';
            echo '  <div class="small text-muted" id="deLormeFilterSummary"></div>';
            echo '  <div class="ms-auto">';
            echo '    <button type="button" id="exportDeLormeCsv" class="btn btn-outline-primary btn-sm">Export CSV</button>';
            echo '  </div>';
            echo '</div>';

            echo '<div class="table-responsive">';
            echo '<table id="deLormeProgressTable.csv" class="table table-striped table-sm align-middle">';
            echo '<thead><tr>';
            echo '<th>Status</th><th>State</th><th>Book</th><th>Page</th><th>First found</th><th>Find count</th><th>Sample cache</th>';
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

                echo '<tr data-status="' . strtolower($row['status']) . '" data-state="' . h($row['stateName']) . '" data-id="' . h($row['id']) . '">';
                echo '<td>' . h($row['status']) . '</td>';
                echo '<td>' . h($row['stateName']) . '</td>';
                echo '<td>' . h($row['bookName']) . '</td>';
                echo '<td>' . h($row['page']) . '</td>';
                echo '<td>' . h($firstFoundLabel) . '</td>';
                echo '<td>' . (int)$row['foundCount'] . '</td>';
                echo '<td>' . $sampleCell . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }
    }
} else {
    if (!$hasDeLormeDataset) {
        echo '<div class="alert alert-warning" role="alert">DeLorme dataset not available yet. Add <code>data/delorme-pages.json</code> before running page lookup.</div>';
    }

    echo '
    <div class="headline">
      <form action="' . ($debugEnabled ? 'gpxdelorme-progress.php?debug=1' : 'gpxdelorme-progress.php') . '" method="post" enctype="multipart/form-data" id="deLormeForm" class="mx-auto" style="max-width: 960px;">
        <div class="mb-3 text-start">
          <label for="targetUsername" class="form-label"><strong>Geocaching username</strong></label>
          <input type="text" class="form-control" id="targetUsername" name="targetUsername" placeholder="Required for strict Found it matching" required>
        </div>
        <input type="file" name="deLormeFiles[]" id="deLormeFiles" class="d-none" accept=".gpx,.zip" multiple>
        <div id="deLormeDropZone" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload GPX or zip files">
          <div class="h5 mb-2">Drop one or more GPX/ZIP files here</div>
          <div class="text-muted mb-0">or click to choose files</div>
          <div id="deLormeSelected" class="small mt-3 text-dark"></div>
        </div>
        <button id="deLormeSubmit" type="submit" class="btn btn-primary mt-3" disabled>Build DeLorme Page Progress</button>
      </form>
    </div>
    ';
}

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $debugEnabled) {
    $elapsedMs = (microtime(true) - $requestStartedAt) * 1000;
    $memoryNowMb = memory_get_usage(true) / 1048576;
    $memoryPeakMb = memory_get_peak_usage(true) / 1048576;
    echo '<div class="alert alert-secondary small" role="status"><strong>Debug:</strong> elapsed ' . h(number_format($elapsedMs, 1)) . ' ms; memory now ' . h(number_format($memoryNowMb, 2)) . ' MB; memory peak ' . h(number_format($memoryPeakMb, 2)) . ' MB';
    foreach ($debugStats as $key => $value) {
      echo '; ' . h($key) . ' ' . h((string)$value);
    }
    echo '.</div>';
  }
?>
  <div id="deLormeProcessingOverlay" class="delorme-processing-overlay" aria-live="polite" aria-hidden="true">
    <div class="card shadow overlay-card">
      <div class="card-body text-center">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
        <div class="h6 mb-1">Processing DeLorme page progress...</div>
        <div class="small text-muted">Large GPX files can take a while. Results will appear automatically.</div>
      </div>
    </div>
  </div>
  <script>
  $(function () {
    var $form = $('#deLormeForm');
    var $input = $('#deLormeFiles');
    var $dropZone = $('#deLormeDropZone');
    var $selected = $('#deLormeSelected');
    var $submit = $('#deLormeSubmit');
    var $processingOverlay = $('#deLormeProcessingOverlay');

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

    var $table = $('#deLormeProgressTable\\.csv');
    var $showMissing = $('#showMissingToggle');
    var $showFound = $('#showFoundToggle');
    var $stateFilter = $('#stateFilter');
    var $summary = $('#deLormeFilterSummary');

    if ($table.length) {
      function applyDeLormeFilters() {
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
          $summary.text(visibleCount + ' of ' + totalCount + ' pages shown');
        }
      }

      $showMissing.on('change', applyDeLormeFilters);
      $showFound.on('change', applyDeLormeFilters);
      $stateFilter.on('change', applyDeLormeFilters);
      applyDeLormeFilters();
    }

    var $exportButton = $('#exportDeLormeCsv');
    if ($table.length && $exportButton.length && typeof $table.table2CSV === 'function') {
      $exportButton.on('click', function () {
        $table.table2CSV({
          delivery: 'download',
          filename: 'delorme-page-progress.csv',
          separator: ','
        });
      });
    }

    if (window.deLormeMapConfig && $('#deLormeProgressMap').length && typeof L !== 'undefined') {
      var mapConfig = window.deLormeMapConfig;
      var visitedIdSet = {};
      var foundCountsByPageId = mapConfig.foundCountsByPageId || {};
      var maxFoundCount = Number(mapConfig.maxFoundCount || 0);
      (mapConfig.visitedIds || []).forEach(function (id) {
        visitedIdSet[String(id)] = true;
      });

      function getFindCountForPage(id) {
        var value = foundCountsByPageId[String(id)];
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

      var deLormeMap = L.map('deLormeProgressMap', {
        preferCanvas: true,
        zoomSnap: 0.5
      }).setView([37.8, -96], 4);

      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 9,
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
      }).addTo(deLormeMap);

      fetch(mapConfig.indexUrl)
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Map dataset request failed with status ' + response.status);
          }
          return response.json();
        })
        .then(function (indexData) {
          var pages = [];
          if (Array.isArray(indexData)) {
            pages = indexData;
          } else if (indexData && Array.isArray(indexData.pages)) {
            pages = indexData.pages;
          }
          var layers = [];
          var allRects = [];
          pages.forEach(function (page) {
            var id = String(page.id);
            var bbox = page.bbox; // [lonMin, latMin, lonMax, latMax]
            if (!bbox || bbox.length < 4) { return; }
            var isVisited = !!visitedIdSet[id];
            var findCount = getFindCountForPage(id);
            var bounds = [[bbox[1], bbox[0]], [bbox[3], bbox[2]]];
            var rect = L.rectangle(bounds, {
              color: '#8c959d',
              weight: 0.5,
              opacity: 0.9,
              fillOpacity: isVisited ? 0.72 : 0.45,
              fillColor: isVisited ? getVisitedFillColor(findCount) : '#dee2e6'
            });
            rect._deLormePageInfo = {
              id: id,
              bookName: page.bookName,
              page: page.page,
              stateName: page.stateName,
              isVisited: isVisited,
              findCount: findCount
            };
            allRects.push(rect);
            layers.push(rect);
          });

          var sharedPopup = L.popup({ maxWidth: 320 });

          allRects.forEach(function (rect) {
            rect.on('click', function (e) {
              var latlng = e.latlng;
              // Collect every rect whose bbox contains the clicked point, so
              // overlapping editions (e.g. Arkansas / Arkansas 2) all appear.
              var matches = allRects.filter(function (r) {
                return r.getBounds().contains(latlng);
              });
              if (!matches.length) { return; }

              var html;
              if (matches.length === 1) {
                var p = matches[0]._deLormePageInfo;
                var statusLabel = p.isVisited ? 'Visited' : 'Missing';
                html = '<strong>' + p.bookName + ' p.\u200b' + p.page + '</strong>' +
                       '<br>State: ' + p.stateName +
                       '<br>Status: ' + statusLabel +
                       '<br>Find count: ' + p.findCount;
              } else {
                // Sort: visited editions first, then alphabetically by bookName.
                matches.sort(function (a, b) {
                  var ai = a._deLormePageInfo, bi = b._deLormePageInfo;
                  if (ai.isVisited !== bi.isVisited) { return ai.isVisited ? -1 : 1; }
                  return ai.bookName < bi.bookName ? -1 : ai.bookName > bi.bookName ? 1 : 0;
                });
                html = '<strong>' + matches[0]._deLormePageInfo.stateName + '</strong>' +
                       '<div style="border-top:1px solid #dee2e6;margin:4px 0"></div>';
                matches.forEach(function (r) {
                  var p = r._deLormePageInfo;
                  var findLabel = p.isVisited
                    ? '<span style="color:#198754">&#10003; Visited</span> &middot; ' +
                      p.findCount + ' find' + (p.findCount !== 1 ? 's' : '')
                    : '<span style="color:#6c757d">Missing</span>';
                  html += '<div style="margin-bottom:4px">' +
                          '<strong>' + p.bookName + '</strong> p.\u200b' + p.page +
                          '<br>' + findLabel +
                          '</div>';
                });
              }

              sharedPopup.setLatLng(latlng).setContent(html).openOn(deLormeMap);
            });
          });

          var group = L.featureGroup(layers).addTo(deLormeMap);
          var groupBounds = group.getBounds();
          if (groupBounds && groupBounds.isValid()) {
            deLormeMap.fitBounds(groupBounds.pad(0.01));
          }
        })
        .catch(function (error) {
          console.error(error);
          $('#deLormeProgressMap').html('<div class="p-3 text-danger">Unable to load DeLorme map data.</div>');
        });
    }
  });
  </script>
<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxdelorme-progress.php')); ?>
