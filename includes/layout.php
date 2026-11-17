<?php

if (!function_exists('layoutEscape')) {
    function layoutEscape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('renderNavbar')) {
    function renderNavbar($activeNav = 'home') {
        $items = array(
            'gpxroadbook' => array('label' => 'CacheAtlas', 'href' => './gpxroadbook.php', 'icon' => 'bi-map'),
            'gpxfriends' => array('label' => 'Caching companions', 'href' => './gpxfriends.php', 'icon' => 'bi-people'),
            'gpxdiff' => array('label' => 'Changed caches', 'href' => './gpxdiff.php', 'icon' => 'bi-intersect'),
            'gpxchallengefiller' => array('label' => 'ChallengeFiller', 'href' => './gpxchallengefiller.php', 'icon' => 'bi-bullseye'),
            'gpxcountyprogress' => array('label' => 'County progress', 'href' => './gpxcounty-progress.php', 'icon' => 'bi-map'),
            'gpxdelormprogress' => array('label' => 'DeLorme progress', 'href' => './gpxdelorme-progress.php', 'icon' => 'bi-map'),
            'gpxhistory' => array('label' => 'History heatmap', 'href' => './gpxhistory.php', 'icon' => 'bi-calendar3'),
            'gpxleaderboard' => array('label' => 'Leaderboard', 'href' => './gpxleaderboard.php', 'icon' => 'bi-trophy'),
            'gpx2csv' => array('label' => 'SpreadsheetMaker', 'href' => './gpx2csv.php', 'icon' => 'bi-filetype-csv'),
        );

        echo '  <header class="mobile-header d-lg-none fixed-top border-bottom">';
        echo '    <div class="container-fluid d-flex align-items-center justify-content-between px-3 py-2">';
        echo '      <a class="navbar-brand mb-0" href="./index.php"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>GPX Pocket</a>';
        echo '      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteSidebar" aria-controls="siteSidebar" aria-label="Open navigation">';
        echo '        <i class="bi bi-list" aria-hidden="true"></i>';
        echo '      </button>';
        echo '    </div>';
        echo '  </header>';

        echo '  <aside class="site-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="siteSidebar" aria-labelledby="siteSidebarLabel">';
        echo '    <div class="offcanvas-header sidebar-header">';
        echo '      <a class="navbar-brand mb-0" id="siteSidebarLabel" href="./index.php"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>GPX Pocket</a>';
        echo '      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#siteSidebar" aria-label="Close navigation"></button>';
        echo '    </div>';
        echo '    <div class="offcanvas-body sidebar-body">';
        echo '      <nav aria-label="Main navigation">';
        echo '        <a class="nav-link' . ($activeNav === 'home' ? ' active fw-semibold' : '') . '" href="./index.php"' . ($activeNav === 'home' ? ' aria-current="page"' : '') . '><i class="bi bi-house me-2" aria-hidden="true"></i>Home</a>';
        echo '        <div class="sidebar-section-label">Tools</div>';
        echo '        <ul class="nav nav-pills flex-column gap-1">';
        foreach ($items as $key => $item) {
            $isActive = ($key === $activeNav);
            echo '          <li class="nav-item"><a class="nav-link' . ($isActive ? ' active fw-semibold' : '') . '" href="' . layoutEscape($item['href']) . '"' . ($isActive ? ' aria-current="page"' : '') . '>';
            echo '<i class="bi ' . layoutEscape($item['icon']) . ' me-2" aria-hidden="true"></i>' . layoutEscape($item['label']) . '</a></li>';
        }
        echo '        </ul>';
        echo '      </nav>';
        echo '      <div class="sidebar-footer mt-auto">';
        echo '        <div class="sidebar-section-label">Geocaching.com</div>';
        echo '        <a class="nav-link" href="https://www.geocaching.com/find/default.aspx" target="_blank" rel="noopener noreferrer">Find another player</a>';
        echo '        <a class="nav-link" href="https://www.geocaching.com/plan/lists" target="_blank" rel="noopener noreferrer">Lists</a>';
        echo '        <a class="nav-link" href="https://www.geocaching.com/pocket/default.aspx" target="_blank" rel="noopener noreferrer">Pocket Queries</a>';
        echo '        <a class="nav-link" href="https://www.geocaching.com/play/geotours" target="_blank" rel="noopener noreferrer">Tours</a>';
        echo '        <a class="nav-link" href="https://github.com/mymaestro/gpxpocket" target="_blank" rel="noopener noreferrer"><i class="bi bi-github me-2" aria-hidden="true"></i>GitHub</a>';
        echo '      </div>';
        echo '    </div>';
        echo '  </aside>';
    }
}

if (!function_exists('renderPageStart')) {
    function renderPageStart($options = array()) {
        $title = isset($options['title']) ? $options['title'] : 'Geocaching Tools';
        $description = isset($options['description']) ? $options['description'] : 'Geocaching GPX tools suite';
        $activeNav = isset($options['activeNav']) ? $options['activeNav'] : 'home';
        $bodyClass = isset($options['bodyClass']) ? trim((string)$options['bodyClass']) : '';
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
        echo '  <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/sandstone/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">';
        echo '  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">';
        echo '  <link href="files/styles.css" rel="stylesheet">';
        echo '  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>';

        if ($extraHeadHtml !== '') {
            echo $extraHeadHtml;
        }

        echo '</head>';
        echo '<body' . ($bodyClass !== '' ? ' class="' . layoutEscape($bodyClass) . '"' : '') . '>';

        renderNavbar($activeNav);

        echo '  <main role="main" class="flex-shrink-0">';
        echo '    <div class="container-fluid px-3 px-md-4">';
    }
}

if (!function_exists('renderPageEnd')) {
    function renderPageEnd($options = array()) {
        $includeFloatingButtons = !empty($options['includeFloatingButtons']);
        $clearPageHref = isset($options['clearPageHref']) ? $options['clearPageHref'] : '';
        $footerVersion = isset($options['footerVersion']) ? $options['footerVersion'] : 'v1.2';

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
