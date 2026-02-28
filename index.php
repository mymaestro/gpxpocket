<?php
require_once __DIR__ . '/includes/layout.php';

renderPageStart(array(
  'title' => 'Geocaching Tools',
  'description' => 'Geocaching GPX tools suite',
  'activeNav' => 'home',
));
?>
  <div class="mx-auto" style="max-width: 1100px;">
      <div class="headline">
        <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/csv.png">
        <h1>Geocaching GPX Toolset</h1>
        <p class="lead">Convert, compare, and analyze your Geocaching Pocket Query GPX files.</p>
      </div>

      <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <div class="col">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-3 lh-1 text-primary"><i class="bi bi-filetype-csv"></i></div>
            <div>
              <h5 class="mb-1">SpreadsheetMaker</h5>
              <p class="mb-2">Upload one GPX/ZIP and export the cache table as CSV or TSV.</p>
              <a href="./gpx2csv.php" class="icon-link">Open <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-3 lh-1 text-primary"><i class="bi bi-intersect"></i></div>
            <div>
              <h5 class="mb-1">Changed caches</h5>
              <p class="mb-2">Compare two Pocket Queries to find new caches, gone caches, and new logs.</p>
              <a href="./gpxdiff.php" class="icon-link">Open <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-3 lh-1 text-primary"><i class="bi bi-calendar3"></i></div>
            <div>
              <h5 class="mb-1">History heatmap</h5>
              <p class="mb-2">Drop many Pocket Queries and view activity over time in a calendar heatmap.</p>
              <a href="./gpxhistory.php" class="icon-link">Open <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-3 lh-1 text-primary"><i class="bi bi-people"></i></div>
            <div>
              <h5 class="mb-1">Caching companions</h5>
              <p class="mb-2">Discover recurring cachers, shared places, and finder activity from Pocket Query logs.</p>
              <a href="./gpxfriends.php" class="icon-link">Open <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-3 lh-1 text-primary"><i class="bi bi-trophy"></i></div>
            <div>
              <h5 class="mb-1">Leaderboard</h5>
              <p class="mb-2">Rank recurring finders across Pocket Query snapshots with linked profile names.</p>
              <a href="./gpxleaderboard.php" class="icon-link">Open <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12 mb-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Additional Utility Ideas</h5>
              <ul class="mb-0">
                <li>Watchlist Alerts (new, disabled, archived, and D/T changes)</li>
                <li>No-Recent-Activity Finder (no logs in X days)</li>
                <li>FTF Opportunity Scan (recent publishes with no found logs)</li>
                <li>Route Optimizer (ordered cache list from a start point)</li>
                <li>County/Region Progress Tracker (coverage and missing areas)</li>
                <li>Challenge Checker (DT grid, year spread, and combo checks)</li>
                <li>Geocaching Friends Finder (identify recurring finders across your Pocket Queries)</li>
                <li>Finder Activity Explorer (lists, timelines, and maps of where selected cachers have been)</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      </div>
<?php renderPageEnd(); ?>
