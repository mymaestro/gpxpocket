<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Geocaching GPX tools suite">
  <meta name="author" content="Warren Gill">
  <title>Geocaching Tools</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='0.9em' font-size='90'%3E%F0%9F%8C%90%3C/text%3E%3C/svg%3E">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="files/styles.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-light bg-light fixed-top border-bottom">
    <a class="navbar-brand" href="#"><i class="bi bi-globe-americas mr-2" aria-hidden="true"></i>Geocaching</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-top" aria-controls="navbar-top" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbar-top">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item active">
          <a class="nav-link" href="./index.php">Home <span class="sr-only">(current)</span></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpx2csv.php">GPX to CSV</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpxdiff.php">GPX Diff</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpxhistory.php">GPX History</a>
        </li>
      </ul>
    </div>
  </nav>

  <main role="main" class="flex-shrink-0">
    <div class="container-fluid px-3 px-md-4">
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
      </div>
    </div>
  </main>

  <footer class="footer mt-auto py-3">
    <div class="container-fluid px-3 px-md-4">
      <span class="text-muted">Copyright 2026 FishParts Media. v1.0</span>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
