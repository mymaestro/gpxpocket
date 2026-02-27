<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Discover recurring cachers from Pocket Query GPX logs">
  <meta name="author" content="Warren Gill">
  <title>Geocaching GPX Friends</title>
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
        <li class="nav-item">
          <a class="nav-link" href="./gpxhistory.php">GPX History</a>
        </li>
        <li class="nav-item active">
          <a class="nav-link" href="./gpxfriends.php">GPX Friends <span class="sr-only">(current)</span></a>
        </li>
      </ul>
    </div>
  </nav>

  <main role="main" class="flex-shrink-0">
    <div class="container-fluid px-3 px-md-4">
      <div class="headline">
        <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/gpx.png">
        <h1>Discover recurring cachers from GPX logs</h1>
        <p class="lead">Build a leaderboard of finder activity and shared cache overlap from Pocket Query GPX files.</p>
      </div>

      <div class="row">
        <div class="col-lg-8 mb-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">MVP scaffold</h5>
              <p class="card-text mb-3">This page is reserved for the new friends feature. Next implementation step is parsing logs by <code>groundspeak:finder</code> and ranking recurring cachers.</p>
              <ul class="mb-0">
                <li>Input: one or more GPX/ZIP files</li>
                <li>Core keying: log <code>id</code> + finder <code>id</code></li>
                <li>Output: leaderboard, shared places, and timeline-ready data</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-4 mb-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">Planning docs</h5>
              <p class="card-text mb-2">Feature planning lives in:</p>
              <ul class="mb-0">
                <li><a href="./files/CACHING_FRIENDS_FEATURE_PLAN.md">CACHING_FRIENDS_FEATURE_PLAN.md</a></li>
                <li><a href="./files/AI_FINDS_INSIGHTS_MVP.md">AI_FINDS_INSIGHTS_MVP.md</a></li>
              </ul>
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
      window.location.href = 'gpxfriends.php';
    });

    $scrollTopButton.on('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
