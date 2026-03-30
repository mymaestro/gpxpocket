<?php
ini_set('memory_limit', '512M');

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';
require_once __DIR__ . '/includes/finds_parser_helpers.php';
require_once __DIR__ . '/includes/county_lookup_helpers.php';
require_once __DIR__ . '/includes/delorme_lookup_helpers.php';
require_once __DIR__ . '/includes/profile_token_helpers.php';
require_once __DIR__ . '/includes/profile_storage_helpers.php';
require_once __DIR__ . '/includes/challengefiller_helpers.php';

$profileContext = profileGetOrCreateTokenContext();
$profileId = !empty($profileContext['ok']) ? $profileContext['profileId'] : '';
$profileLoad = array('ok' => false, 'exists' => false, 'findsByCode' => array(), 'meta' => array(), 'error' => '');
if ($profileId !== '') {
  $profileLoad = profileStorageLoad($profileId);
}

$extraHeadHtml = <<<'HTML'
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script src="files/table2CSV.js"></script>
  <style>
    #opportunityMap {
      height: 500px;
      border: 1px solid #ddd;
      border-radius: 0.25rem;
      margin-bottom: 1rem;
    }
    .leaflet-popup-content {
      min-width: 280px;
    }
    .cache-popup {
      font-size: 0.875rem;
    }
    .cache-popup-checkbox {
      margin-top: 0.5rem;
      padding-top: 0.5rem;
      border-top: 1px solid #dee2e6;
    }
    .cache-kind-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 0.25rem;
      margin-top: 0.4rem;
    }
    .cache-kind-badge {
      display: inline-block;
      padding: 0.1rem 0.35rem;
      border-radius: 999px;
      font-size: 0.72rem;
      line-height: 1.2;
      color: #fff;
      font-weight: 600;
    }
    .map-legend {
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid #d1d5db;
      border-radius: 0.35rem;
      padding: 0.5rem 0.6rem;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
      font-size: 0.75rem;
      color: #111827;
      min-width: 210px;
    }
    .map-legend-title {
      font-weight: 700;
      margin-bottom: 0.35rem;
    }
    .map-legend-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.4rem;
      margin: 0.2rem 0;
    }
    .map-legend-chip {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      flex: 0 0 auto;
      border: 2px solid #111827;
    }
    .map-legend-sep {
      border-top: 1px solid #e5e7eb;
      margin: 0.45rem 0;
    }
    .upload-spinner-overlay {
      position: fixed;
      inset: 0;
      background: rgba(17, 24, 39, 0.35);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      backdrop-filter: blur(1px);
    }
    .upload-spinner-overlay.is-visible {
      display: flex;
    }
    .upload-spinner-card {
      background: #fff;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      padding: 1rem 1.2rem;
      display: flex;
      align-items: center;
      gap: 0.8rem;
      min-width: 300px;
      max-width: 90vw;
    }
    .upload-spinner-text {
      font-size: 0.95rem;
      color: #111827;
      line-height: 1.3;
    }
  </style>
HTML;

renderPageStart(array(
    'title' => 'ChallengeFiller Opportunity Seeker',
    'description' => 'Find high-value caches from a regional pocket query based on missing challenge progress',
    'activeNav' => 'gpxchallengefiller',
    'extraHeadHtml' => $extraHeadHtml,
));

$message = '';
$targetUsername = trim((string)(isset($_POST['targetUsername']) ? $_POST['targetUsername'] : ''));

$countyGeojsonPath = __DIR__ . '/data/us-counties.geojson';
$countyLookupMessage = '';
$countyFeatures = loadCountyGeojsonFeatures($countyGeojsonPath, $countyLookupMessage);
$hasCountyDataset = count($countyFeatures) > 0;

$deLormePath = __DIR__ . '/data/delorme-pages.json';
$deLormeDataDir = __DIR__ . '/data/delorme';
$deLormeMessage = '';
$deLormePages = loadDeLormePages($deLormePath, $deLormeMessage);
$hasDeLormeDataset = count($deLormePages) > 0;

$opportunityRows = array();
$coverage = null;
$myFindsCount = 0;
$regionCount = 0;
$runCompleted = false;
$missingDtCount = 0;
$foundCountyCount = 0;
$foundDeLormeCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxUploadBytes = 100 * 1024 * 1024;

    if (empty($profileContext['ok'])) {
        echo '<div class="alert alert-danger" role="alert">' . h($profileContext['error']) . '</div>';
    } else {
        $action = trim((string)(isset($_POST['action']) ? $_POST['action'] : ''));
        if ($action === 'reset-profile') {
            $deleted = profileStorageDelete($profileId);
            if (!empty($deleted['ok'])) {
                $newToken = profileRotateTokenCookie();
                if ($newToken !== '') {
                    $profileId = profileIdFromToken($newToken);
            } else {
              // Fallback if response headers are already committed.
              $profileId = !empty($profileContext['profileId']) ? $profileContext['profileId'] : $profileId;
                }
                echo '<div class="alert alert-success" role="alert">Stored My Finds profile reset. Upload a new My Finds file to continue.</div>';
                $profileLoad = profileStorageLoad($profileId);
            } else {
                echo '<div class="alert alert-danger" role="alert">' . h($deleted['error']) . '</div>';
            }
        } else {
            $myFindsUploads = normalizeUploadArray(isset($_FILES['myFindsFiles']) ? $_FILES['myFindsFiles'] : array());
            $regionUploads = normalizeUploadArray(isset($_FILES['regionFiles']) ? $_FILES['regionFiles'] : array());

            $myFindsHasUpload = false;
            foreach ($myFindsUploads as $upload) {
                if (isset($upload['error']) && (int)$upload['error'] === UPLOAD_ERR_OK) {
                    $myFindsHasUpload = true;
                    break;
                }
            }

            $regionHasUpload = false;
            foreach ($regionUploads as $upload) {
                if (isset($upload['error']) && (int)$upload['error'] === UPLOAD_ERR_OK) {
                    $regionHasUpload = true;
                    break;
                }
            }

            $myFindsByCode = (!empty($profileLoad['ok']) && !empty($profileLoad['exists'])) ? $profileLoad['findsByCode'] : array();

            if ($action === 'save-my-finds') {
              if (!$myFindsHasUpload) {
                echo '<div class="alert alert-danger" role="alert">Upload at least one My Finds GPX/ZIP file to save your baseline.</div>';
              } elseif ($targetUsername === '') {
                echo '<div class="alert alert-danger" role="alert">Enter your geocaching username when uploading My Finds.</div>';
              } else {
                $parsedUploads = array();
                $uploadedMyFinds = array();

                foreach ($myFindsUploads as $upload) {
                  $parsed = extractGpxFromUpload($upload, $maxUploadBytes, $message);
                  if (!$parsed) {
                    continue;
                  }
                  $parsedUploads[] = $parsed;

                  $fileFinds = parseFoundCachesFromGpx($parsed['source'], $parsed['name'], $targetUsername, $message);
                  foreach ($fileFinds as $code => $find) {
                    if (!isset($uploadedMyFinds[$code]) || $find['firstFoundTs'] < $uploadedMyFinds[$code]['firstFoundTs']) {
                      $uploadedMyFinds[$code] = $find;
                    }
                  }
                }

                foreach ($parsedUploads as $parsed) {
                  cleanupExtracted($parsed);
                }
                unset($parsedUploads, $fileFinds, $parsed);
                if (function_exists('gc_collect_cycles')) {
                  gc_collect_cycles();
                }

                if (count($uploadedMyFinds) < 1) {
                  echo '<div class="alert alert-warning" role="alert">No matching Found it logs found for ' . h($targetUsername) . ' in uploaded My Finds. ' . h($message) . '</div>';
                } else {
                  $saved = profileStorageSaveMyFinds($profileId, $uploadedMyFinds, array(
                    'finderName' => $targetUsername,
                    'sourceLabel' => 'ChallengeFiller My Finds upload',
                  ));
                  if (!empty($saved['ok'])) {
                    $profileLoad = profileStorageLoad($profileId);
                    $myFindsByCode = $profileLoad['findsByCode'];
                    echo '<div class="alert alert-success" role="alert">Stored ' . count($myFindsByCode) . ' My Finds entries. Next step: upload regional PQ files and click Find Opportunities.</div>';
                  } else {
                    echo '<div class="alert alert-danger" role="alert">Unable to store My Finds profile: ' . h($saved['error']) . '</div>';
                  }
                }
              }
            } elseif ($action === 'run') {
              if ($myFindsHasUpload) {
                echo '<div class="alert alert-warning" role="alert">For faster runs, upload My Finds first using "Save/Update My Finds Baseline", then run regional scoring in a separate step.</div>';
              } elseif (count($myFindsByCode) < 1) {
                echo '<div class="alert alert-warning" role="alert">Upload a My Finds pocket query first to build your baseline challenge profile.</div>';
              } elseif (!$regionHasUpload) {
                echo '<div class="alert alert-danger" role="alert">Upload at least one regional pocket query to score opportunities.</div>';
              } else {
                $parsedRegionUploads = array();
                $regionCachesByCode = array();

                foreach ($regionUploads as $upload) {
                  $parsed = extractGpxFromUpload($upload, $maxUploadBytes, $message);
                  if (!$parsed) {
                    continue;
                  }
                  $parsedRegionUploads[] = $parsed;

                  $regionRows = parseRegionCachesFromGpx($parsed['source'], $parsed['name'], $message);
                  foreach ($regionRows as $code => $row) {
                    if (!isset($regionCachesByCode[$code])) {
                      $regionCachesByCode[$code] = $row;
                    }
                  }
                }

                foreach ($parsedRegionUploads as $parsed) {
                  cleanupExtracted($parsed);
                }
                unset($parsedRegionUploads, $regionRows, $parsed);
                if (function_exists('gc_collect_cycles')) {
                  gc_collect_cycles();
                }

                if (count($regionCachesByCode) < 1) {
                  echo '<div class="alert alert-warning" role="alert">No cache waypoints found in regional uploads. ' . h($message) . '</div>';
                } else {
                  if (!empty($profileLoad['ok']) && !empty($profileLoad['exists'])) {
                    profileStorageTouchAccessed($profileId);
                  }

                  $coverage = cfBuildCoverageFromMyFinds(
                    $myFindsByCode,
                    $hasCountyDataset ? $countyFeatures : array(),
                    $hasDeLormeDataset ? $deLormePages : array(),
                    $deLormeDataDir
                  );

                  $opportunityRows = cfScoreRegionCandidates(
                    $regionCachesByCode,
                    $myFindsByCode,
                    $coverage,
                    $hasCountyDataset ? $countyFeatures : array(),
                    $hasDeLormeDataset ? $deLormePages : array(),
                    $deLormeDataDir
                  );

                  $missingDtCount = isset($coverage['missingDtByKey']) ? count($coverage['missingDtByKey']) : 0;
                  $foundCountyCount = isset($coverage['foundCountyByFips']) ? count($coverage['foundCountyByFips']) : 0;
                  $foundDeLormeCount = isset($coverage['foundDelormeById']) ? count($coverage['foundDelormeById']) : 0;

                  $myFindsCount = count($myFindsByCode);
                  $regionCount = count($regionCachesByCode);
                  $runCompleted = true;

                  // Free large arrays after scoring is complete
                  unset($regionCachesByCode, $coverage, $countyFeatures, $deLormePages);
                  if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                  }
                }
              }
            } else {
              echo '<div class="alert alert-danger" role="alert">Unknown action requested.</div>';
            }
        }
    }
}
?>
<div class="headline">
  <h1>ChallengeFiller Opportunity Seeker</h1>
  <p class="lead">Upload My Finds once, then upload one or more regional pocket queries to rank caches by challenge value.</p>
</div>

<?php
if (!$hasCountyDataset) {
    echo '<div class="alert alert-warning" role="alert">County dataset unavailable (' . h($countyLookupMessage) . '). County opportunities are temporarily disabled.</div>';
}
if (!$hasDeLormeDataset) {
    echo '<div class="alert alert-warning" role="alert">DeLorme dataset unavailable (' . h($deLormeMessage) . '). DeLorme opportunities are temporarily disabled.</div>';
}
if (!empty($message)) {
    echo '<div class="alert alert-secondary" role="status">Parser notes: ' . h($message) . '</div>';
}

$profileHasData = !empty($profileLoad['ok']) && !empty($profileLoad['exists']) && !empty($profileLoad['findsByCode']);
$profileFinder = ($profileHasData && !empty($profileLoad['meta']['finderName'])) ? $profileLoad['meta']['finderName'] : '';
$profileUpdatedAt = ($profileHasData && !empty($profileLoad['meta']['updatedAt'])) ? $profileLoad['meta']['updatedAt'] : '';
?>

<div class="card mb-4">
  <div class="card-body">
    <h5 class="card-title mb-3">Profile Baseline</h5>
    <?php if ($profileHasData) { ?>
      <div class="small text-muted mb-3">
        Stored My Finds profile: <strong><?php echo h((string)count($profileLoad['findsByCode'])); ?></strong> caches
        <?php if ($profileFinder !== '') { ?>
          for <strong><?php echo h($profileFinder); ?></strong>
        <?php } ?>
        <?php if ($profileUpdatedAt !== '') { ?>
          (updated <?php echo h($profileUpdatedAt); ?> UTC)
        <?php } ?>.
      </div>
    <?php } else { ?>
      <div class="small text-muted mb-3">No stored My Finds baseline yet.</div>
    <?php } ?>

    <form id="challengeFillerForm" action="gpxchallengefiller.php" method="post" enctype="multipart/form-data" class="mx-auto" style="max-width: 980px;">
      <div class="row g-3">
        <div class="col-12">
          <label for="targetUsername" class="form-label"><strong>Geocaching username</strong></label>
          <input type="text" class="form-control" id="targetUsername" name="targetUsername" value="<?php echo h($targetUsername !== '' ? $targetUsername : $profileFinder); ?>" placeholder="Required only when uploading new My Finds">
          <div class="form-text">Used to validate Found it ownership when ingesting My Finds files.</div>
        </div>

        <div class="col-12">
          <label for="myFindsFiles" class="form-label"><strong>Step 1: My Finds GPX/ZIP</strong></label>
          <input type="file" class="form-control" id="myFindsFiles" name="myFindsFiles[]" accept=".gpx,.zip" multiple>
          <div class="form-text">Upload these first, then click "Save/Update My Finds Baseline".</div>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <button type="submit" name="action" value="save-my-finds" class="btn btn-outline-primary">Save/Update My Finds Baseline</button>
          </div>
        </div>

        <div class="col-12">
          <label for="regionFiles" class="form-label"><strong>Step 2: Regional PQ GPX/ZIP</strong></label>
          <input type="file" class="form-control" id="regionFiles" name="regionFiles[]" accept=".gpx,.zip" multiple>
          <div class="form-text">After baseline is saved, upload regional files and click "Find Opportunities".</div>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <button type="submit" name="action" value="run" class="btn btn-primary">Find Opportunities</button>
            <?php if ($profileHasData) { ?>
              <button type="submit" name="action" value="reset-profile" class="btn btn-outline-danger" onclick="return confirm('Delete stored My Finds baseline and rotate token cookie?');">Reset Stored Profile</button>
            <?php } ?>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<div id="uploadSpinnerOverlay" class="upload-spinner-overlay" aria-hidden="true" aria-live="polite">
  <div class="upload-spinner-card" role="status">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <div class="upload-spinner-text">
      Uploading and processing files...
      <div class="small text-muted">Large ZIP uploads can take a minute.</div>
    </div>
  </div>
</div>

<?php
if ($runCompleted) {
    echo '<div class="row g-3 mb-4">';
    echo '  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">My Finds baseline</div><div class="display-6">' . (int)$myFindsCount . '</div></div></div></div>';
    echo '  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Regional caches</div><div class="display-6">' . (int)$regionCount . '</div></div></div></div>';
    echo '  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Opportunity caches</div><div class="display-6">' . (int)count($opportunityRows) . '</div></div></div></div>';
    echo '  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Missing D/T cells</div><div class="display-6">' . (int)$missingDtCount . '</div></div></div></div>';
    echo '</div>';

    echo '<div class="alert alert-info alert-dismissible fade show" role="status">';
    echo 'Current coverage snapshot: ' . (int)$foundCountyCount . ' counties found, ' . (int)$foundDeLormeCount . ' DeLorme pages found, ' . (int)$missingDtCount . ' D/T cells missing.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';

    if (count($opportunityRows) < 1) {
        echo '<div class="alert alert-success" role="alert">No unmet county/DeLorme/DT opportunities found in the regional upload set.</div>';
    } else {
        echo '<h5 class="mt-4 mb-3">Opportunity Map</h5>';
        echo '<div id="opportunityMap"></div>';
        
        echo '<div class="mb-3">';
        echo '  <button type="button" id="selectAllCaches" class="btn btn-sm btn-outline-secondary">Select All</button>';
        echo '  <button type="button" id="deselectAllCaches" class="btn btn-sm btn-outline-secondary">Deselect All</button>';
        echo '  <span class="badge bg-secondary ms-2">Selected: <span id="selectedCount">0</span>/' . count($opportunityRows) . '</span>';
        echo '</div>';

        echo '<div class="d-flex justify-content-between mb-3">';
        echo '  <div>';
        echo '    <button type="button" id="exportSelectedCsv" class="btn btn-outline-primary btn-sm" disabled>Export Selected CSV (<span id="exportSelectedCount">0</span>)</button>';
        echo '  </div>';
        echo '  <div>';
        echo '    <button type="button" id="exportTableCsv" class="btn btn-outline-primary btn-sm">Export All CSV</button>';
        echo '  </div>';
        echo '</div>';

        echo '<div class="table-responsive">';
        echo '<table id="challengeFillerTable.csv" class="table table-striped table-sm align-middle">';
        echo '<thead><tr>';
        echo '<th style="width: 40px;"><input type="checkbox" id="selectAllTable" title="Select all table rows"></th>';
        echo '<th>Score</th><th>GC Code</th><th>Cache</th><th>Type</th><th>D/T</th><th>Signals</th><th>Source PQ</th>';
        echo '</tr></thead><tbody>';

        foreach ($opportunityRows as $row) {
            $code = $row['cacheCode'];
            $codeCell = h($code);
            if ($row['cacheUrl'] !== '' && filter_var($row['cacheUrl'], FILTER_VALIDATE_URL)) {
                $codeCell = '<a href="' . h($row['cacheUrl']) . '" target="_blank" rel="noopener">' . h($code) . '</a>';
            }

            $signalLabels = array();
            foreach ($row['signals'] as $signal) {
                $kind = strtoupper((string)$signal['kind']);
                $signalLabels[] = $kind . ': ' . (string)$signal['label'];
            }

            echo '<tr data-cache-code="' . h($code) . '">';
            echo '<td><input type="checkbox" class="cache-row-checkbox" value="' . h($code) . '" title="Select ' . h($code) . '"></td>';
            echo '<td><strong>' . (int)$row['score'] . '</strong></td>';
            echo '<td>' . $codeCell . '</td>';
            echo '<td>' . h($row['cacheName']) . '</td>';
            echo '<td>' . h($row['cacheType']) . '</td>';
            echo '<td>' . h((string)$row['difficulty']) . ' / ' . h((string)$row['terrain']) . '</td>';
            echo '<td>' . h(implode(' | ', $signalLabels)) . '</td>';
            echo '<td>' . h((string)$row['sourceName']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
?>

<script>
$(function () {
  var $form = $('#challengeFillerForm');
  if (!$form.length) {
    return;
  }

  $form.on('submit', function (event) {
    var submitter = (event.originalEvent && event.originalEvent.submitter) ? event.originalEvent.submitter : document.activeElement;
    var action = submitter && submitter.value ? String(submitter.value) : '';
    var myFindCount = ($('#myFindsFiles')[0] && $('#myFindsFiles')[0].files) ? $('#myFindsFiles')[0].files.length : 0;
    var regionCount = ($('#regionFiles')[0] && $('#regionFiles')[0].files) ? $('#regionFiles')[0].files.length : 0;
    var shouldShowSpinner = (action === 'save-my-finds' && myFindCount > 0) || (action === 'run' && regionCount > 0);

    if (!shouldShowSpinner) {
      return true;
    }

    $('#uploadSpinnerOverlay').addClass('is-visible').attr('aria-hidden', 'false');
    if (submitter) {
      $form.find('button[type="submit"]').not(submitter).prop('disabled', true);
    }
    return true;
  });
});

var opportunityCaches = <?php if ($runCompleted && count($opportunityRows) > 0) { echo json_encode($opportunityRows, JSON_UNESCAPED_SLASHES); } else { echo '[]'; } ?>;
var selectedCaches = new Set();

$(function () {
  if (opportunityCaches.length === 0) {
    return;
  }

  // Initialize map
  var bounds = [];
  opportunityCaches.forEach(function (cache) {
    bounds.push([cache.lat, cache.lon]);
  });

  var map = L.map('opportunityMap').fitBounds(bounds, { padding: [50, 50] });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  var maxScore = opportunityCaches.reduce(function (acc, cache) {
    var score = Number(cache.score) || 0;
    return Math.max(acc, score);
  }, 0);

  var challengeMeta = {
    county: { label: 'County', short: 'CO', color: '#1d4ed8' },
    delorme: { label: 'DeLorme', short: 'DL', color: '#047857' },
    dt: { label: 'D/T', short: 'DT', color: '#b45309' }
  };

  function getUniqueSignalKinds(cache) {
    var seen = {};
    var kinds = [];
    (cache.signals || []).forEach(function (signal) {
      var kind = (signal && signal.kind) ? String(signal.kind).toLowerCase() : '';
      if (!kind || seen[kind]) {
        return;
      }
      seen[kind] = true;
      kinds.push(kind);
    });
    return kinds;
  }

  function getKindBadgeHtml(kinds) {
    if (!kinds || kinds.length === 0) {
      return '';
    }
    var badges = kinds.map(function (kind) {
      var meta = challengeMeta[kind] || { label: kind.toUpperCase(), short: kind.slice(0, 2).toUpperCase(), color: '#4b5563' };
      return '<span class="cache-kind-badge" style="background:' + meta.color + '">' + htmlEscape(meta.short) + '</span>';
    }).join('');
    return '<div class="cache-kind-badges">' + badges + '</div>';
  }

  function getBorderStyleForKinds(kinds) {
    if (!kinds || kinds.length === 0) {
      return { color: '#374151', dashArray: null };
    }
    if (kinds.length === 1) {
      var meta = challengeMeta[kinds[0]];
      return {
        color: meta ? meta.color : '#374151',
        dashArray: null
      };
    }
    return {
      color: '#111827',
      dashArray: '4 3'
    };
  }

  function getMarkerStyle(score, scoreMax, kinds) {
    var safeScore = Math.max(0, Number(score) || 0);
    var safeMax = Math.max(1, Number(scoreMax) || 1);

    // Log normalization keeps the scale useful if future challenge scoring expands.
    var normalized = Math.log(safeScore + 1) / Math.log(safeMax + 1);
    normalized = Math.max(0, Math.min(1, normalized));

    // Blue (low) -> Green -> Yellow -> Orange/Red (high)
    var hue = 220 - (210 * normalized);
    var fillColor = 'hsl(' + hue.toFixed(0) + ', 85%, 48%)';
    var borderStyle = getBorderStyleForKinds(kinds);

    return {
      radius: 6 + Math.round(normalized * 8),
      fillColor: fillColor,
      color: borderStyle.color,
      weight: 2,
      opacity: 0.9,
      fillOpacity: 0.75,
      dashArray: borderStyle.dashArray
    };
  }

  function scoreFillColor(score, scoreMax) {
    var safeScore = Math.max(0, Number(score) || 0);
    var safeMax = Math.max(1, Number(scoreMax) || 1);
    var normalized = Math.log(safeScore + 1) / Math.log(safeMax + 1);
    normalized = Math.max(0, Math.min(1, normalized));
    var hue = 220 - (210 * normalized);
    return 'hsl(' + hue.toFixed(0) + ', 85%, 48%)';
  }

  function addMapLegend(mapRef, scoreMax) {
    var legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
      var div = L.DomUtil.create('div', 'map-legend');
      var midScore = Math.max(1, Math.round(scoreMax / 2));
      var highScore = Math.max(1, scoreMax);
      div.innerHTML =
        '<div class="map-legend-title">Marker Legend</div>' +
        '<div class="map-legend-row"><span>Low score (1)</span><span class="map-legend-chip" style="background:' + scoreFillColor(1, scoreMax) + ';border-color:#111827"></span></div>' +
        '<div class="map-legend-row"><span>Mid score (' + midScore + ')</span><span class="map-legend-chip" style="background:' + scoreFillColor(midScore, scoreMax) + ';border-color:#111827"></span></div>' +
        '<div class="map-legend-row"><span>High score (' + highScore + ')</span><span class="map-legend-chip" style="background:' + scoreFillColor(highScore, scoreMax) + ';border-color:#111827"></span></div>' +
        '<div class="map-legend-sep"></div>' +
        '<div class="map-legend-row"><span>County only</span><span class="map-legend-chip" style="background:#fff;border-color:' + challengeMeta.county.color + '"></span></div>' +
        '<div class="map-legend-row"><span>DeLorme only</span><span class="map-legend-chip" style="background:#fff;border-color:' + challengeMeta.delorme.color + '"></span></div>' +
        '<div class="map-legend-row"><span>D/T only</span><span class="map-legend-chip" style="background:#fff;border-color:' + challengeMeta.dt.color + '"></span></div>' +
        '<div class="map-legend-row"><span>Mixed challenges</span><span class="map-legend-chip" style="background:#fff;border-color:#111827;border-style:dashed"></span></div>';
      return div;
    };
    legend.addTo(mapRef);
  }

  addMapLegend(map, maxScore);

  // Add markers for each opportunity cache
  opportunityCaches.forEach(function (cache, index) {
    var signalKinds = getUniqueSignalKinds(cache);
    var marker = L.circleMarker([cache.lat, cache.lon], getMarkerStyle(cache.score, maxScore, signalKinds));
    var kindLabel = signalKinds.length > 0
      ? signalKinds.map(function (kind) {
          var meta = challengeMeta[kind] || { short: kind.slice(0, 2).toUpperCase() };
          return meta.short;
        }).join(' / ')
      : 'None';

    var popupContent = '<div class="cache-popup">' +
      '<strong><a href="https://coord.info/' + encodeURIComponent(cache.cacheCode) + '" target="_blank" rel="noopener">' + htmlEscape(cache.cacheCode) + '</a></strong><br>' +
      '<small>' + htmlEscape(cache.cacheName) + '</small><br>' +
      '<div style="margin-top: 0.5rem;"><small>' +
      'Score: <strong>' + cache.score + '</strong><br>' +
      'Challenges: <strong>' + htmlEscape(kindLabel) + '</strong><br>' +
      'D/T: ' + cache.difficulty + '/' + cache.terrain + '<br>' +
      'Type: ' + htmlEscape(cache.cacheType) +
      '</small></div>' +
      getKindBadgeHtml(signalKinds) +
      '<div class="cache-popup-checkbox">' +
      '<label style="margin: 0;"><input type="checkbox" class="map-cache-checkbox" value="' + htmlEscape(cache.cacheCode) + '" data-index="' + index + '"> Select</label>' +
      '</div>' +
      '</div>';

    marker.bindPopup(popupContent);
    marker.on('popupopen', function () {
      var $checkbox = $('[data-index="' + index + '"]');
      if (selectedCaches.has(cache.cacheCode)) {
        $checkbox.prop('checked', true);
      }
      $checkbox.on('change', function () {
        toggleCacheSelection(cache.cacheCode);
      });
    });
    marker.addTo(map);
  });

  // Table checkbox handlers
  $('#selectAllTable').on('change', function () {
    var isChecked = $(this).prop('checked');
    opportunityCaches.forEach(function (cache) {
      if (isChecked) {
        selectedCaches.add(cache.cacheCode);
      } else {
        selectedCaches.delete(cache.cacheCode);
      }
      updateTableCheckbox(cache.cacheCode, isChecked);
    });
    updateSelectedCount();
  });

  $('.cache-row-checkbox').on('change', function () {
    var code = $(this).val();
    if ($(this).prop('checked')) {
      selectedCaches.add(code);
    } else {
      selectedCaches.delete(code);
      $('#selectAllTable').prop('checked', false);
    }
    updateSelectedCount();
  });

  // Map selection buttons
  $('#selectAllCaches').on('click', function () {
    opportunityCaches.forEach(function (cache) {
      selectedCaches.add(cache.cacheCode);
      updateTableCheckbox(cache.cacheCode, true);
    });
    $('#selectAllTable').prop('checked', true);
    updateSelectedCount();
  });

  $('#deselectAllCaches').on('click', function () {
    selectedCaches.clear();
    opportunityCaches.forEach(function (cache) {
      updateTableCheckbox(cache.cacheCode, false);
    });
    $('#selectAllTable').prop('checked', false);
    updateSelectedCount();
  });

  // Export handlers
  $('#exportSelectedCsv').on('click', function () {
    if (selectedCaches.size === 0) {
      alert('Select at least one cache to export.');
      return;
    }
    exportSelectedAsCSV();
  });

  $('#exportTableCsv').on('click', function () {
    if (typeof $table !== 'undefined' && typeof $table.table2CSV === 'function') {
      $table.table2CSV({ filename: 'challengefiller-all.csv' });
    }
  });

  updateSelectedCount();
});

function htmlEscape(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

function toggleCacheSelection(cacheCode) {
  if (selectedCaches.has(cacheCode)) {
    selectedCaches.delete(cacheCode);
  } else {
    selectedCaches.add(cacheCode);
  }
  updateTableCheckbox(cacheCode, selectedCaches.has(cacheCode));
  updateSelectedCount();
}

function updateTableCheckbox(cacheCode, isChecked) {
  $('[data-cache-code="' + cacheCode + '"] .cache-row-checkbox').prop('checked', isChecked);
}

function updateSelectedCount() {
  var count = selectedCaches.size;
  $('#selectedCount').text(count);
  $('#exportSelectedCount').text(count);
  $('#exportSelectedCsv').prop('disabled', count === 0);
}

function exportSelectedAsCSV() {
  if (selectedCaches.size === 0) {
    return;
  }

  var selectedRows = [];
  opportunityCaches.forEach(function (row) {
    if (selectedCaches.has(row.cacheCode)) {
      selectedRows.push(row);
    }
  });

  var csv = 'GC Code,Cache Name,Type,Difficulty,Terrain,Container,Score,Signals,Source PQ,Latitude,Longitude\n';
  selectedRows.forEach(function (row) {
    var signals = row.signals.map(function (s) { return s.kind.toUpperCase() + ': ' + s.label; }).join(' | ');
    csv += '"' + row.cacheCode + '",' +
      '"' + escapeCSV(row.cacheName) + '",' +
      '"' + row.cacheType + '",' +
      row.difficulty + ',' +
      row.terrain + ',' +
      '"' + row.container + '",' +
      row.score + ',' +
      '"' + escapeCSV(signals) + '",' +
      '"' + escapeCSV(row.sourceName) + '",' +
      row.lat + ',' +
      row.lon + '\n';
  });

  var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'challengefiller-selected.csv';
  link.click();
}

function escapeCSV(value) {
  if (value.indexOf('"') >= 0 || value.indexOf(',') >= 0 || value.indexOf('\n') >= 0) {
    return '"' + value.replace(/"/g, '""') + '"';
  }
  return value;
}

var $table = $('#challengeFillerTable\\.csv');
if ($table.length && typeof $table.table2CSV === 'function') {
  // Legacy export functionality (kept for compatibility if needed)
}
</script>
<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxchallengefiller.php')); ?>
