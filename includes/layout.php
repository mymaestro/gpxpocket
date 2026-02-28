<?php

if (!function_exists('layoutEscape')) {
    function layoutEscape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('renderNavbar')) {
    function renderNavbar($activeNav = 'home') {
        $items = array(
            'home' => array('label' => 'Home', 'href' => './index.php'),
            'gpx2csv' => array('label' => 'GPX to CSV', 'href' => './gpx2csv.php'),
            'gpxdiff' => array('label' => 'GPX Diff', 'href' => './gpxdiff.php'),
            'gpxhistory' => array('label' => 'GPX History', 'href' => './gpxhistory.php'),
            'gpxfriends' => array('label' => 'GPX Friends', 'href' => './gpxfriends.php'),
            'gpxleaderboard' => array('label' => 'GPX Leaderboard', 'href' => './gpxleaderboard.php'),
        );

        echo '    <nav class="navbar navbar-expand-md navbar-light bg-light fixed-top border-bottom">';
        echo '      <div class="container-fluid px-3 px-md-4">';
        echo '      <a class="navbar-brand" href="#"><i class="bi bi-globe-americas me-2" aria-hidden="true"></i>Geocaching</a>';
        echo '      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-top" aria-controls="navbar-top" aria-expanded="false" aria-label="Toggle navigation">';
        echo '        <span class="navbar-toggler-icon"></span>';
        echo '      </button>';
        echo '      <div class="collapse navbar-collapse" id="navbar-top">';
        echo '        <ul class="navbar-nav me-auto">';


        foreach ($items as $key => $item) {
            $isActive = ($key === $activeNav);
            echo '        <li class="nav-item' . ($isActive ? ' active' : '') . '">';
            echo '          <a class="nav-link" href="' . layoutEscape($item['href']) . '">' . layoutEscape($item['label']);
            if ($isActive) {
                echo ' <span class="visually-hidden">(current)</span>';
            }
            echo '</a>';
            echo '        </li>';
        }

        echo '        <li class="nav-item dropdown">';
        echo '          <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-bs-toggle="dropdown" aria-expanded="false">On geocaching.com</a>';
        echo '          <div class="dropdown-menu" aria-labelledby="dropdown01">';
        echo '            <a class="dropdown-item" href="https://www.geocaching.com/find/default.aspx" target="_blank" rel="noopener">Find Another Player</a>';
        echo '            <a class="dropdown-item" href="https://www.geocaching.com/plan/lists" target="_blank" rel="noopener">Lists</a>';
        echo '            <a class="dropdown-item" href="https://www.geocaching.com/pocket/default.aspx" target="_blank" rel="noopener">Pocket Queries</a>';
        echo '            <a class="dropdown-item" href="https://www.geocaching.com/play/geotours" target="_blank" rel="noopener">Tours</a>';
        echo '          </div>';
        echo '        </li>';

        echo '      </ul>';
        echo '        <ul class="navbar-nav ms-md-3">';
        echo '          <li class="nav-item">';
        echo '            <a class="nav-link" href="https://github.com/mymaestro/gpxpocket" target="_blank" rel="noopener"><i class="bi bi-github me-1" aria-hidden="true"></i>GitHub</a>';
        echo '          </li>';
        echo '        </ul>';
        echo '    </div>';
        echo '    </div>';
        echo '  </nav>';
    }
}

if (!function_exists('renderPageStart')) {
    function renderPageStart($options = array()) {
        $title = isset($options['title']) ? $options['title'] : 'Geocaching Tools';
        $description = isset($options['description']) ? $options['description'] : 'Geocaching GPX tools suite';
        $activeNav = isset($options['activeNav']) ? $options['activeNav'] : 'home';
        $extraHeadHtml = isset($options['extraHeadHtml']) ? $options['extraHeadHtml'] : '';

        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '  <meta charset="utf-8">';
        echo '  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">';
        echo '  <meta name="description" content="' . layoutEscape($description) . '">';
        echo '  <meta name="author" content="Warren Gill">';
        echo '  <title>' . layoutEscape($title) . '</title>';
        echo '  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ctext y=\'0.9em\' font-size=\'90%\'%3E%F0%9F%8C%90%3C/text%3E%3C/svg%3E">';
        echo '  <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">';
        echo '  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">';
        echo '  <link href="files/styles.css" rel="stylesheet">';
        echo '  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>';

        if ($extraHeadHtml !== '') {
            echo $extraHeadHtml;
        }

        echo '</head>';
        echo '<body>';

        renderNavbar($activeNav);

        echo '  <main role="main" class="flex-shrink-0">';
        echo '    <div class="container-fluid px-3 px-md-4">';
    }
}

if (!function_exists('renderPageEnd')) {
    function renderPageEnd($options = array()) {
        $includeFloatingButtons = !empty($options['includeFloatingButtons']);
        $clearPageHref = isset($options['clearPageHref']) ? $options['clearPageHref'] : '';
        $footerVersion = isset($options['footerVersion']) ? $options['footerVersion'] : 'v1.1';

        echo '    </div>';
        echo '  </main>';

        echo '  <footer class="footer mt-auto py-3">';
        echo '    <div class="container-fluid px-3 px-md-4">';
        echo '      <span class="text-muted">Copyright 2026 FishParts Media. ' . layoutEscape($footerVersion) . '</span>';
        echo '    </div>';
        echo '  </footer>';

        if ($includeFloatingButtons) {
            echo '  <button id="clearPageButton" type="button" class="btn btn-danger floating-action-button clear-page-button" aria-label="Start over" title="Start over">';
            echo '    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">';
            echo '      <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>';
            echo '      <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1zm-3.5 0v-.5h-6V3z"/>';
            echo '    </svg>';
            echo '  </button>';
            echo '  <button id="scrollTopButton" type="button" class="btn btn-primary floating-action-button scroll-top-button" aria-label="Scroll to top" title="Scroll to top">';
            echo '    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">';
            echo '      <path fill-rule="evenodd" d="M7.646 4.646a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8 5.707 5.354 8.354a.5.5 0 1 1-.708-.708z"/>';
            echo '    </svg>';
            echo '  </button>';

            echo '  <script>';
            echo '  $(function () {';
            echo '    var $clearPageButton = $("#clearPageButton");';
            echo '    var $scrollTopButton = $("#scrollTopButton");';
            echo '    if (!$scrollTopButton.length || !$clearPageButton.length) {';
            echo '      return;';
            echo '    }';
            echo '    function updateFloatingButtonVisibility() {';
            echo '      if ($(window).scrollTop() > 220) {';
            echo '        $scrollTopButton.addClass("is-visible");';
            echo '        $clearPageButton.addClass("is-visible");';
            echo '      } else {';
            echo '        $scrollTopButton.removeClass("is-visible");';
            echo '        $clearPageButton.removeClass("is-visible");';
            echo '      }';
            echo '    }';
            echo '    $(window).on("scroll", updateFloatingButtonVisibility);';
            echo '    updateFloatingButtonVisibility();';
            echo '    $clearPageButton.on("click", function () {';
            echo '      window.location.href = ' . json_encode($clearPageHref) . ';';
            echo '    });';
            echo '    $scrollTopButton.on("click", function () {';
            echo '      window.scrollTo({ top: 0, behavior: "smooth" });';
            echo '    });';
            echo '  });';
            echo '  </script>';
        }

        echo '  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>';
        echo '</body>';
        echo '</html>';
    }
}
