<?php
require_once __DIR__ . '/includes/layout.php';

renderPageStart(array(
  'title' => 'Geocaching Tools',
  'description' => 'Geocaching GPX tools suite',
  'activeNav' => 'home',
));
?>
      <div class="headline">
        <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/csv.png">
        <h1>Geocaching GPX Toolset</h1>
        <p class="lead">Convert, compare, and analyze your Geocaching Pocket Query GPX files.</p>
      </div>

      <div class="row">
        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">GPX to CSV</h5>
              <p class="card-text">Upload one GPX/ZIP and export the cache table as CSV or TSV.</p>
              <a href="./gpx2csv.php" class="btn btn-primary mt-auto">Open Tool</a>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">GPX Diff</h5>
              <p class="card-text">Compare two Pocket Queries to find new caches, gone caches, and new logs.</p>
              <a href="./gpxdiff.php" class="btn btn-primary mt-auto">Open Tool</a>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">GPX History</h5>
              <p class="card-text">Drop many Pocket Queries and view activity over time in a calendar heatmap.</p>
              <a href="./gpxhistory.php" class="btn btn-primary mt-auto">Open Tool</a>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">GPX Friends</h5>
              <p class="card-text">Discover recurring cachers, shared places, and finder activity from Pocket Query logs.</p>
              <a href="./gpxfriends.php" class="btn btn-primary mt-auto">Open Tool</a>
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
<?php renderPageEnd(); ?>
