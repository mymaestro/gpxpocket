<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';
require_once __DIR__ . '/includes/roadbook_helpers.php';

$extraHeadHtml = <<<'HTML'
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@6.18.0/dist/maplibre-gl.css">
  <style>
    @page {
      size: 11in 8.5in;
      margin: 0;
    }

    body {
      background: #f7f7f7;
      color: #111;
    }

    .roadbook-upload {
      max-width: 760px;
      margin: 2rem auto 0;
    }

    .roadbook-print-actions {
      box-sizing: border-box;
      display: flex;
      width: 11in;
      max-width: calc(100vw - 2rem);
      justify-content: flex-end;
      margin: 1rem auto;
    }

    .roadbook-page {
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      width: 11in;
      height: 8.5in;
      min-height: 0;
      page-break-after: always;
      break-after: page;
      background: white;
      border: 1px solid #333;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
      padding: 0.35in;
      margin: 1.2rem auto;
      overflow: hidden;
    }

    .roadbook-page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #111;
      margin-bottom: 0.5rem;
      padding-bottom: 0.25rem;
      font-size: 0.8rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-weight: 700;
    }

    .roadbook-layout {
      display: flex;
      flex: 1 1 auto;
      gap: 0.4in;
      min-height: 0;
      overflow: hidden;
    }

    .roadbook-map-panel {
      flex: 1 1 0;
      min-width: 0;
      border: 1px solid #111;
      background: #f5f5f5;
      position: relative;
      overflow: hidden;
    }

    .roadbook-map {
      width: 100%;
      height: 100%;
      background: #fff;
    }

    .roadbook-map .maplibregl-canvas {
      display: block;
    }

    .roadbook-map .maplibregl-ctrl-scale {
      border-color: #111;
      color: #111;
      background: rgba(255, 255, 255, 0.9);
    }

    .roadbook-map .maplibregl-ctrl-attrib {
      background: rgba(255, 255, 255, 0.9);
      font-size: 0.5rem;
    }

    .roadbook-map-message {
      position: absolute;
      z-index: 1000;
      top: 50%;
      left: 50%;
      max-width: 80%;
      padding: 0.3rem 0.5rem;
      transform: translate(-50%, -50%);
      border: 1px solid #777;
      background: rgba(255, 255, 255, 0.95);
      color: #222;
      font-size: 0.7rem;
      text-align: center;
    }

    .roadbook-marker-icon {
      border: 0;
      background: transparent;
    }

    .roadbook-marker-icon span {
      display: flex;
      width: 34px;
      height: 34px;
      align-items: center;
      justify-content: center;
      box-sizing: border-box;
      border: 1.5px solid #111;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.95);
      color: #111;
      font-size: 0.65rem;
      font-weight: 700;
    }

    .roadbook-cache-list {
      flex: 1 1 0;
      min-width: 0;
      min-height: 0;
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
      justify-content: flex-start;
      overflow: hidden;
    }

    .roadbook-cache-item {
      box-sizing: border-box;
      flex: 1 1 0;
      min-height: 0;
      border: 1px solid #111;
      padding: 0.2rem 0.3rem;
      background: #fff;
      overflow: hidden;
      page-break-inside: avoid;
    }

    .roadbook-cache-header {
      display: flex;
      justify-content: space-between;
      gap: 0.3rem;
      border-bottom: 1px solid #111;
      padding-bottom: 0.1rem;
      margin-bottom: 0.1rem;
      font-weight: 700;
      font-size: 0.68rem;
    }

    .roadbook-cache-number {
      font-size: 0.7rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      flex: 0 0 auto;
    }

    .roadbook-cache-heading {
      display: flex;
      align-items: baseline;
      gap: 0.35rem;
      min-width: 0;
      flex: 1;
    }

    .roadbook-cache-name {
      font-size: 0.68rem;
      min-width: 0;
      white-space: normal;
      overflow-wrap: break-word;
    }

    .roadbook-cache-code {
      flex: 0 0 auto;
    }

    .roadbook-coordinates {
      font-size: 0.62rem;
      line-height: 1.1;
      font-weight: 600;
      margin-bottom: 0;
    }

    .roadbook-region {
      font-size: 0.58rem;
      line-height: 1.1;
      margin-bottom: 0;
      font-weight: 600;
    }

    .roadbook-meta {
      font-size: 0.58rem;
      line-height: 1.1;
      margin-bottom: 0;
    }

    .roadbook-log-block {
      border-top: 1px solid #111;
      margin-top: 0.1rem;
      padding-top: 0.1rem;
    }

    .roadbook-log-line {
      font-size: 0.54rem;
      line-height: 1.05;
      margin-bottom: 0;
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .roadbook-log-type {
      display: inline-block;
      min-width: 2.4em;
      margin-right: 0.1rem;
      border: 1px solid #111;
      padding: 0 0.1rem;
      text-align: center;
      font-size: 0.48rem;
      font-weight: 700;
      vertical-align: 0.05em;
    }

    .roadbook-log-type-dnf {
      border-style: double;
    }

    .roadbook-log-type-detail {
      margin-right: 0.1rem;
      font-size: 0.5rem;
    }

    .roadbook-log-user {
      font-weight: 700;
    }

    .roadbook-warning {
      margin-top: 0.1rem;
      font-size: 0.58rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      border-top: 1px solid #111;
      padding-top: 0.1rem;
    }

    .roadbook-empty {
      font-size: 0.8rem;
      color: #444;
      padding: 1rem;
    }

    @media print {
      body {
        background: white;
      }

      main,
      footer {
        margin-left: 0 !important;
      }

      main > .container-fluid {
        padding: 0 !important;
      }

      .roadbook-upload,
      .roadbook-print-actions,
      .clear-page-button,
      .scroll-top-button,
      .mobile-header,
      .site-sidebar,
      footer,
      .floating-action-button {
        display: none !important;
      }

      .roadbook-page {
        margin: 0;
        border: 0;
        box-shadow: none;
      }

      .roadbook-map,
      .roadbook-map canvas {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
  </style>
HTML;

renderPageStart(array(
  'title' => 'CacheAtlas',
  'description' => 'Printable monochrome GPX CacheAtlas',
  'activeNav' => 'gpxroadbook',
  'extraHeadHtml' => $extraHeadHtml,
));

$messages = array();
$maxRoadbookCaches = 50;
$processedCaches = array();
$uploadedFileTitle = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
  $upload = $_FILES['fileToUpload'];
  $uploadedFileTitle = pathinfo(
    basename(str_replace('\\', '/', (string)$upload['name'])),
    PATHINFO_FILENAME
  );
  $parsed = extractGpxFromUpload($upload, 10 * 1024 * 1024, $message);
  if ($parsed === null) {
    $messages[] = $message;
  } else {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($parsed['source'], 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors();

    if ($xml === false || strtolower($xml->getName()) !== 'gpx') {
      $messages[] = 'The uploaded file did not parse as a valid GPX document.';
    } else {
      $processedCaches = gpxRoadbookParseGpxNode($xml, $maxRoadbookCaches);
      if (count($processedCaches) === 0) {
        $messages[] = 'No geocache waypoints were found in the uploaded GPX file.';
      } else {
        $clusterCount = count(gpxRoadbookClusterCaches($processedCaches, 3, 7));
        $messages[] = 'CacheAtlas ready. Showing ' . count($processedCaches) . ' cache' . (count($processedCaches) === 1 ? '' : 's') . ' across ' . $clusterCount . ' clustered map page' . ($clusterCount === 1 ? '' : 's') . '.';
      }
    }

    cleanupExtracted($parsed);
  }
}

if (!empty($messages)) {
  echo '  <div class="alert alert-warning roadbook-upload" role="alert">' . implode('<br>', array_map('h', $messages)) . '</div>';
}

if (empty($processedCaches)) {
  echo '  <div class="roadbook-upload">
            <form id="cacheAtlasUploadForm" action="gpxroadbook.php" method="post" enctype="multipart/form-data" class="card shadow-sm border-0">
              <div class="card-body p-4">
                <h1 class="h3 mb-3">CacheAtlas</h1>
                <p class="text-muted mb-4">Upload a GPX or ZIP from a Pocket Query to generate a monochrome, print-ready CacheAtlas.</p>
                <div class="mb-3">
                  <input type="file" name="fileToUpload" id="fileToUpload" accept=".gpx,.zip" class="form-control" required>
                </div>
                <button type="submit" id="cacheAtlasGenerateButton" class="btn btn-dark">
                  Generate CacheAtlas
                  <span id="cacheAtlasUploadSpinner" class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                </button>
                <div id="cacheAtlasUploadStatus" class="small text-muted mt-2 d-none" role="status" aria-live="polite">
                  Uploading your GPX and building CacheAtlas…
                </div>
              </div>
            </form>
          </div>
          <script>
            (function () {
              var form = document.getElementById("cacheAtlasUploadForm");
              var button = document.getElementById("cacheAtlasGenerateButton");
              var spinner = document.getElementById("cacheAtlasUploadSpinner");
              var status = document.getElementById("cacheAtlasUploadStatus");
              form.addEventListener("submit", function (event) {
                event.preventDefault();
                button.disabled = true;
                spinner.classList.remove("d-none");
                status.classList.remove("d-none");

                fetch(form.action, {
                  method: form.method,
                  body: new FormData(form),
                  credentials: "same-origin"
                })
                  .then(function (response) {
                    if (!response.ok) {
                      throw new Error("The server returned HTTP " + response.status + ".");
                    }
                    return response.text();
                  })
                  .then(function (html) {
                    if (html.trim() === "") {
                      throw new Error("The server returned an empty page.");
                    }
                    var responseDocument = new DOMParser().parseFromString(html, "text/html");
                    var responseMain = responseDocument.querySelector("main");
                    if (!responseMain) {
                      throw new Error("The server response did not contain the CacheAtlas page.");
                    }
                    var scripts = Array.from(responseMain.querySelectorAll("script"));
                    scripts.forEach(function (script) {
                      script.remove();
                    });
                    document.title = responseDocument.title;
                    document.querySelector("main").replaceWith(responseMain);
                    scripts.forEach(function (script) {
                      var activeScript = document.createElement("script");
                      Array.from(script.attributes).forEach(function (attribute) {
                        activeScript.setAttribute(attribute.name, attribute.value);
                      });
                      activeScript.textContent = script.textContent;
                      document.body.appendChild(activeScript);
                    });
                  })
                  .catch(function (error) {
                    console.error("CacheAtlas upload failed:", error);
                    button.disabled = false;
                    spinner.classList.add("d-none");
                    status.classList.remove("d-none");
                    status.textContent = "Upload failed. Check your connection and try again.";
                  });
              });
            }());
          </script>';
} else {
  $clusters = gpxRoadbookClusterCaches($processedCaches, 3, 7);
  $roadbookMapConfigs = array();

  foreach ($clusters as $clusterIndex => $cluster) {
    $bounds = gpxRoadbookComputeClusterBounds($cluster);
    $mapId = 'roadbook-map-' . ($clusterIndex + 1);
    $markers = array();
    foreach ($cluster as $cacheIndex => $cache) {
      $markers[] = array(
        'lat' => (float)$cache['lat'],
        'lon' => (float)$cache['lon'],
        'label' => '[' . ($cacheIndex + 1) . ']',
      );
    }
    $roadbookMapConfigs[] = array(
      'id' => $mapId,
      'bounds' => array(
        array((float)$bounds['minLat'], (float)$bounds['minLon']),
        array((float)$bounds['maxLat'], (float)$bounds['maxLon']),
      ),
      'markers' => $markers,
    );
    $pageTitle = 'CacheAtlas page ' . ($clusterIndex + 1) . ' / ' . count($clusters);

    echo '  <article class="roadbook-page">
            <div class="roadbook-page-header">
              <span>' . h($pageTitle) . '</span>
              <span>' . h($uploadedFileTitle) . '</span>
            </div>
            <div class="roadbook-layout">
              <div class="roadbook-map-panel">
                <div class="roadbook-map" id="' . h($mapId) . '" role="img" aria-label="Map for cache cluster ' . ($clusterIndex + 1) . '">
                  <div class="roadbook-map-message">Loading monochrome vector map…</div>
                </div>
              </div>
              <div class="roadbook-cache-list">';

    foreach ($cluster as $cacheIndex => $cache) {
      $cacheName = trim((string)$cache['name']);
      $cacheNumber = $cacheIndex + 1;
      $context = gpxRoadbookResolvePlaceContext($cache['lat'], $cache['lon']);
      $cache['state'] = $context['state'];
      $cache['county'] = $context['county'];
      $regionText = trim(($cache['county'] !== '' ? $cache['county'] : '') . ($cache['county'] !== '' && $cache['state'] !== '' ? ', ' : '') . $cache['state']);
      if ($regionText === '') {
        $regionText = 'Location unverified';
      }

      $logWarnings = gpxRoadbookConsecutiveDnfWarning($cache['logs']);
      $logSnips = gpxRoadbookSummarizeLogs($cache['logs'], array());

      echo '        <div class="roadbook-cache-item">
                      <div class="roadbook-cache-header">
                        <div class="roadbook-cache-heading">
                          <span class="roadbook-cache-number">[' . $cacheNumber . ']</span>
                          <span class="roadbook-cache-name">' . h($cacheName) . '</span>
                        </div>
                        <span class="roadbook-cache-code">' . h($cache['code']) . '</span>
                      </div>
                      <div class="roadbook-coordinates">' . h(DECtoDMS($cache['lat'], $cache['lon'])) . '</div>
                      <div class="roadbook-region">' . h($regionText) . '</div>
                      <div class="roadbook-meta">D/T ' . h($cache['difficulty']) . ' / ' . h($cache['terrain']) . '&nbsp;&nbsp;|&nbsp;&nbsp; ' . h($cache['container']) . '</div>
                      <div class="roadbook-log-block">';

      if (empty($logSnips)) {
        echo '          <span class="roadbook-log-line">No recent logs.</span>';
      } else {
        foreach ($logSnips as $logEntry) {
          $finderLabel = $logEntry['finder'] !== '' ? '<span class="roadbook-log-user">' . h($logEntry['finder']) . '</span>' . h($logEntry['finderCount']) : '<span class="roadbook-log-user">Unknown</span>';
          $logTypeLabel = strtoupper($logEntry['kind']);
          $logTypeDetail = $logEntry['kind'] === 'other' && $logEntry['type'] !== ''
            ? '<span class="roadbook-log-type-detail">' . h($logEntry['type']) . '</span>'
            : '';
          echo '          <span class="roadbook-log-line"><span class="roadbook-log-type roadbook-log-type-' . h($logEntry['kind']) . '">' . h($logTypeLabel) . '</span>' . $logTypeDetail . $finderLabel . ' <span class="roadbook-log-snippet">' . h($logEntry['snippet']) . '</span></span>';
        }
      }

      if ($logWarnings !== '') {
        echo '          <div class="roadbook-warning">' . h($logWarnings) . '</div>';
      }

      echo '        </div>
                    </div>';
    }

    echo '          </div>
                  </div>
                </article>';
  }

  $mapConfigsJson = json_encode(
    $roadbookMapConfigs,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
  );
  echo '<div class="roadbook-print-actions">
          <button type="button" class="btn btn-dark" id="cacheAtlasPrintButton" disabled>Print / Save PDF</button>
          <span class="align-self-center ms-3 small text-muted d-inline-flex align-items-center" id="cacheAtlasPrintStatus" role="status" aria-live="polite">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="cacheAtlasPrintSpinner" aria-hidden="true"></span>
            <span id="cacheAtlasPrintStatusText"></span>
          </span>
        </div>';
  echo '<script type="module">
    (function () {
      var mapConfigs = ' . $mapConfigsJson . ';
      var mapInstances = [];
      var printButton = document.getElementById("cacheAtlasPrintButton");
      var printStatus = document.getElementById("cacheAtlasPrintStatusText");
      var printSpinner = document.getElementById("cacheAtlasPrintSpinner");
      var attribution = "&copy; <a href=\"https://openfreemap.org/\">OpenFreeMap</a> | &copy; <a href=\"https://www.openmaptiles.org/\">OpenMapTiles</a> | &copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap contributors</a>";

      import("https://cdn.jsdelivr.net/npm/maplibre-gl@6.18.0/dist/maplibre-gl.mjs")
        .then(function (maplibregl) {
          initializeMaps(maplibregl);
        })
        .catch(function () {
          document.querySelectorAll(".roadbook-map-message").forEach(function (message) {
            message.textContent = "Map unavailable: MapLibre could not be loaded.";
          });
          printButton.disabled = true;
          printStatus.textContent = "Map library unavailable.";
        });

      function createPrintStyle() {
        return {
          version: 8,
          glyphs: "https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf",
          sources: {
            openfreemap: {
              type: "vector",
              url: "https://tiles.openfreemap.org/planet",
              attribution: attribution
            }
          },
          layers: [
            {
              id: "background",
              type: "background",
              paint: { "background-color": "#ffffff" }
            },
            {
              id: "parks",
              type: "fill",
              source: "openfreemap",
              "source-layer": "park",
              paint: { "fill-color": "#f4f4f4" }
            },
            {
              id: "landcover",
              type: "fill",
              source: "openfreemap",
              "source-layer": "landcover",
              filter: ["match", ["get", "class"], ["wood", "grass", "scrub"], true, false],
              paint: { "fill-color": "#f7f7f7", "fill-opacity": 0.8 }
            },
            {
              id: "water",
              type: "fill",
              source: "openfreemap",
              "source-layer": "water",
              paint: { "fill-color": "#e6e6e6" }
            },
            {
              id: "waterways",
              type: "line",
              source: "openfreemap",
              "source-layer": "waterway",
              layout: { "line-cap": "round", "line-join": "round" },
              paint: {
                "line-color": "#bcbcbc",
                "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.4, 14, 1.4, 18, 2.5]
              }
            },
            {
              id: "buildings",
              type: "fill",
              source: "openfreemap",
              "source-layer": "building",
              minzoom: 13,
              paint: { "fill-color": "#f8f8f8", "fill-outline-color": "#dedede" }
            },
            {
              id: "local-roads",
              type: "line",
              source: "openfreemap",
              "source-layer": "transportation",
              filter: ["match", ["get", "class"], ["minor", "residential", "service", "track", "path", "living_street", "pedestrian"], true, false],
              layout: { "line-cap": "round", "line-join": "round" },
              paint: {
                "line-color": "#aaaaaa",
                "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.3, 10, 0.6, 14, 1.2, 18, 2.2]
              }
            },
            {
              id: "secondary-roads",
              type: "line",
              source: "openfreemap",
              "source-layer": "transportation",
              filter: ["match", ["get", "class"], ["secondary", "secondary_link", "tertiary", "tertiary_link"], true, false],
              layout: { "line-cap": "round", "line-join": "round" },
              paint: {
                "line-color": "#777777",
                "line-width": ["interpolate", ["linear"], ["zoom"], 6, 0.5, 10, 1, 14, 2, 18, 3.2]
              }
            },
            {
              id: "state-roads",
              type: "line",
              source: "openfreemap",
              "source-layer": "transportation",
              filter: ["match", ["get", "class"], ["trunk", "trunk_link", "primary", "primary_link"], true, false],
              layout: { "line-cap": "round", "line-join": "round" },
              paint: {
                "line-color": "#333333",
                "line-width": ["interpolate", ["linear"], ["zoom"], 6, 0.8, 10, 1.6, 14, 2.8, 18, 4.5]
              }
            },
            {
              id: "major-road-casing",
              type: "line",
              source: "openfreemap",
              "source-layer": "transportation",
              filter: ["match", ["get", "class"], ["motorway", "motorway_link"], true, false],
              layout: { "line-cap": "round", "line-join": "round" },
              paint: {
                "line-color": "#111111",
                "line-width": ["interpolate", ["linear"], ["zoom"], 6, 1.8, 10, 3.2, 14, 5.2, 18, 8]
              }
            },
            {
              id: "major-roads",
              type: "line",
              source: "openfreemap",
              "source-layer": "transportation",
              filter: ["match", ["get", "class"], ["motorway", "motorway_link"], true, false],
              layout: { "line-cap": "round", "line-join": "round" },
              paint: {
                "line-color": "#444444",
                "line-width": ["interpolate", ["linear"], ["zoom"], 6, 0.8, 10, 1.8, 14, 3.2, 18, 5.5]
              }
            },
            {
              id: "water-labels",
              type: "symbol",
              source: "openfreemap",
              "source-layer": "water_name",
              layout: {
                "text-field": ["coalesce", ["get", "name_en"], ["get", "name"]],
                "text-font": ["Noto Sans Italic"],
                "text-size": 10,
                "text-max-width": 8
              },
              paint: {
                "text-color": "#666666",
                "text-halo-color": "#ffffff",
                "text-halo-width": 1
              }
            },
            {
              id: "road-labels",
              type: "symbol",
              source: "openfreemap",
              "source-layer": "transportation_name",
              minzoom: 12,
              filter: ["match", ["get", "class"], ["motorway", "trunk", "primary", "secondary", "tertiary"], true, false],
              layout: {
                "symbol-placement": "line",
                "text-field": ["coalesce", ["get", "name_en"], ["get", "name"]],
                "text-font": ["Noto Sans Regular"],
                "text-size": 9,
                "text-max-angle": 30
              },
              paint: {
                "text-color": "#333333",
                "text-halo-color": "#ffffff",
                "text-halo-width": 1.2
              }
            },
            {
              id: "place-labels",
              type: "symbol",
              source: "openfreemap",
              "source-layer": "place",
              minzoom: 5,
              filter: ["match", ["get", "class"], ["city", "town", "village", "hamlet"], true, false],
              layout: {
                "text-field": ["coalesce", ["get", "name_en"], ["get", "name"]],
                "text-font": ["Noto Sans Regular"],
                "text-size": ["interpolate", ["linear"], ["zoom"], 5, 9, 13, 12],
                "text-max-width": 8
              },
              paint: {
                "text-color": "#222222",
                "text-halo-color": "#ffffff",
                "text-halo-width": 1.4
              }
            }
          ]
        };
      }

      function initializeMaps(maplibregl) {
        mapConfigs.forEach(function (config) {
        var element = document.getElementById(config.id);
        var message = element.querySelector(".roadbook-map-message");
        var mapHadError = false;
        var map = new maplibregl.Map({
          container: element,
          style: createPrintStyle(),
          interactive: false,
          preserveDrawingBuffer: true,
          attributionControl: { compact: false },
          fadeDuration: 0,
          renderWorldCopies: false
        });
        map.addControl(new maplibregl.ScaleControl({
          maxWidth: 100,
          unit: "imperial"
        }), "bottom-left");

        map.on("error", function () {
          mapHadError = true;
          if (message) {
            message.textContent = "Some vector map data could not be loaded.";
          }
        });

        map.on("load", function () {
          var routeCoordinates = config.markers.map(function (marker) {
            return [marker.lon, marker.lat];
          });
          map.addSource("cacheatlas-route", {
            type: "geojson",
            data: {
              type: "Feature",
              properties: {},
              geometry: { type: "LineString", coordinates: routeCoordinates }
            }
          });
          map.addLayer({
            id: "cacheatlas-route",
            type: "line",
            source: "cacheatlas-route",
            paint: {
              "line-color": "#555555",
              "line-width": 1.5,
              "line-dasharray": [2, 2]
            }
          }, "place-labels");

          config.markers.forEach(function (marker) {
            var markerElement = document.createElement("div");
            markerElement.className = "roadbook-marker-icon";
            markerElement.setAttribute("aria-hidden", "true");
            var markerLabel = document.createElement("span");
            markerLabel.textContent = marker.label;
            markerElement.appendChild(markerLabel);
            new maplibregl.Marker({
              element: markerElement,
              anchor: "center"
            }).setLngLat([marker.lon, marker.lat]).addTo(map);
          });

          map.fitBounds([
            [config.bounds[0][1], config.bounds[0][0]],
            [config.bounds[1][1], config.bounds[1][0]]
          ], {
            padding: 24,
            maxZoom: 16,
            duration: 0
          });
          map.once("idle", function () {
            element.setAttribute("data-map-ready", mapHadError ? "error" : "true");
            if (message && !mapHadError) {
              message.remove();
            }
          });
        });
          mapInstances.push({ map: map, element: element });
        });

        function waitForMapIdle(mapInstance) {
          return new Promise(function (resolve, reject) {
          var timeoutId = window.setTimeout(function () {
            mapInstance.map.off("idle", onIdle);
            reject(new Error("Map did not become idle."));
          }, 20000);
          function onIdle() {
            window.clearTimeout(timeoutId);
            if (mapInstance.element.getAttribute("data-map-ready") !== "true") {
              reject(new Error("Map data is not ready."));
              return;
            }
            resolve();
          }
          mapInstance.map.once("idle", onIdle);
          mapInstance.map.resize();
          mapInstance.map.triggerRepaint();
          });
        }

        printButton.addEventListener("click", function () {
        printButton.disabled = true;
        printStatus.textContent = "Preparing vector maps for printing…";
        printSpinner.classList.remove("d-none");
        Promise.all(mapInstances.map(waitForMapIdle))
          .then(function () {
            printStatus.textContent = "";
          printSpinner.classList.add("d-none");
            window.print();
          })
          .catch(function () {
            printStatus.textContent = "Maps are not ready. Check your connection and try again.";
          printSpinner.classList.add("d-none");
          })
          .then(function () {
            printButton.disabled = false;
          });
        });
        printButton.disabled = false;

        window.addEventListener("beforeprint", function () {
          mapInstances.forEach(function (mapInstance) {
            mapInstance.map.resize();
          });
        });

        window.addEventListener("afterprint", function () {
          mapInstances.forEach(function (mapInstance) {
            mapInstance.map.resize();
          });
        });
      }
    }());
  </script>';
}

renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpxroadbook.php'));
?>
