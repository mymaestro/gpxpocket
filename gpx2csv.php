<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="CSV from Geocaching GPX">
  <meta name="author" content="Warren Gill">
  <meta name="generator" content="Jekyll v4.0.1">
  <title>Geocaching GPX to CSV</title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='0.9em' font-size='90'%3E%F0%9F%8C%90%3C/text%3E%3C/svg%3E">
  
  <!-- Bootstrap core CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Custom styles for this template -->
  <link href="files/styles.css" rel="stylesheet">
  <!-- Include jquery -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
  <script src="files/table2CSV.js"></script>
  <script>
  $(document).ready(function () {
    $('table').each(function () {
      var $table = $(this);
      var tableId = $table.attr('id') || 'geocaches.csv';
      var baseName = tableId.replace(/\.csv$/i, '');

      var $buttonRow = $('<div class="mt-3 mb-2">');
      var $csvButton = $('<button type="button" class="btn btn-primary mr-2">Export CSV</button>');
      var $tsvButton = $('<button type="button" class="btn btn-outline-primary">Export TSV</button>');
      var $exportHelp = $('<div class="small text-muted mt-2">TSV is often best for LibreOffice/OpenOffice imports.</div>');

      $buttonRow.append($csvButton, $tsvButton);
      $buttonRow.insertAfter($table);
      $exportHelp.insertAfter($buttonRow);

      $csvButton.click(function () {
        $table.table2CSV({
          delivery: 'download',
          filename: tableId,
          separator: ','
        });
      });

      $tsvButton.click(function () {
        $table.table2CSV({
          delivery: 'download',
          filename: baseName + '.tsv',
          separator: '\t'
        });
      });
    });
  });
  </script>
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
        <li class="nav-item active">
          <a class="nav-link" href="./gpx2csv.php">GPX to CSV <span class="sr-only">(current)</span></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpxdiff.php">GPX Diff</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpxhistory.php">GPX History</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./gpxfriends.php">GPX Friends</a>
        </li>
        <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">On geocaching.com</a>
        <div class="dropdown-menu" aria-labelledby="dropdown01">
          <a class="dropdown-item" href="https://www.geocaching.com/plan/lists" target="_blank">Lists</a>
          <a class="dropdown-item" href="https://www.geocaching.com/pocket/default.aspx" target="_blank">Pocket Queries</a>
          <a class="dropdown-item" href="https://www.geocaching.com/play/geotours" target="_blank">Tours</a>
        </div>
      </li>
      </ul>
    </div>
  </nav>
  <main role="main" class="flex-shrink-0">
    <div class="container-fluid px-3 px-md-4">
    <div class="headline">
      <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/csv.png">
      <h1>Create a spreadsheet from a Geocaching list</h1>
      <p class="lead">Take a GPX file from a Geocaching List or Pocket Query<br>Get a table with critical information.</p>
    </div>
<?php
$message = '';
function endswith($haystack, $needle) {
    $strlen = strlen($haystack);
    $testlen = strlen($needle);
    if ($testlen > $strlen) return false;
    return substr_compare($haystack, $needle, $strlen - $testlen, $testlen) === 0;
}
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// Process the uploaded file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fileSource = '';
  $fileZip = '';
  $fileName = '';
  $fileExt = '';
  $fileBase = '';
  $fileSize = 0;
  $maxUploadBytes = 10 * 1024 * 1024;

  $uploadOk = 1; // 1 = good to go, 0 = no good

  // Check if the uploaded file is a valid XML file
  if (isset($_POST["submit"])) {
    if (!isset($_FILES["fileToUpload"])) {
      $uploadOk = 0;
      $message .= "No file uploaded. ";
    } elseif ($_FILES["fileToUpload"]['error'] === UPLOAD_ERR_OK) {
      $fileSource = $_FILES["fileToUpload"]["tmp_name"];             // Temporary upload source
      $fileName = basename($_FILES["fileToUpload"]["name"]);         // Upload file basename (with ext)
      $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));   // File extension (must be "gpx" or "zip")
      $fileBase = basename($fileName, '.' . pathinfo($fileName, PATHINFO_EXTENSION));
      $fileSize = (int)$_FILES["fileToUpload"]["size"];

      if (!is_uploaded_file($fileSource)) {
        $uploadOk = 0;
        $message .= "Invalid upload source. ";
      }

      if ($uploadOk == 1 && $fileSize <= 0) {
        $uploadOk = 0;
        $message .= "Uploaded file is empty. ";
      }

      if ($uploadOk == 1 && $fileSize > $maxUploadBytes) {
        $uploadOk = 0;
        $message .= "File exceeds 10MB upload limit. ";
      }

      // Allow only gpx format
      if ($uploadOk == 1 && $fileExt != "gpx" ) {
        if ($fileExt == "zip") {
          $filePath = pathinfo(realpath($fileSource), PATHINFO_DIRNAME);
          $zip = new ZipArchive;
          $foundGpx = false;
          if ($zip->open($fileSource) === true) {
            for ($f = 0; $f < $zip->numFiles; $f++) {
              $zfileName = $zip->getNameIndex($f);
              if ($zfileName === false || strpos($zfileName, "\0") !== false) {
                continue;
              }

              $zfileBase = basename($zfileName);
              if ($zfileBase !== $zfileName && strpos($zfileName, '/') !== false) {
                continue;
              }

              if (endswith(strtolower($zfileBase), '.gpx') && !endswith(strtolower($zfileBase), 'wpts.gpx')) {
                $zfileContents = $zip->getFromIndex($f);
                if ($zfileContents !== false) {
                  $tmpExtracted = tempnam($filePath, 'gpx_');
                  if ($tmpExtracted !== false && file_put_contents($tmpExtracted, $zfileContents) !== false) {
                    $fileZip = $fileSource;
                    $fileSource = $tmpExtracted;
                    $fileExt = 'gpx';
                    $fileBase = pathinfo($zfileBase, PATHINFO_FILENAME);
                    $fileSize = filesize($fileSource);
                    $foundGpx = true;
                    break;
                  }
                }
              }
            }
            $zip->close();

            if (!$foundGpx) {
              $message .= "No GPX file found in zip archive.";
              $uploadOk = 0;
            }
          } else {
            $message .= "Failed to open zip file.";
            $uploadOk = 0;
          }
        } else {      
          $message .= "Only GPX files are allowed. " . $fileExt . " extension rejected.";
          $uploadOk = 0;
        }
      }

      if ($uploadOk == 1) {
        libxml_use_internal_errors(true);
        $xmlProbe = simplexml_load_file($fileSource, 'SimpleXMLElement', LIBXML_NONET);
        if ($xmlProbe === false || strtolower($xmlProbe->getName()) !== 'gpx') {
          $message .= "Not a valid GPX file!";
          $uploadOk = 0;
        }
        libxml_clear_errors();
      }
    } else {
      $uploadOk = 0;
      $message .= "Error uploading file. ";
      $error = $_FILES["fileToUpload"]['error'];
      switch ($error) {
        case UPLOAD_ERR_INI_SIZE :
          $message .= "File exceeds upload max file size.";
          break;
        case UPLOAD_ERR_PARTIAL :
          $message .= "File partially uploaded.";
          break;
        default:
          $message .= "other unknown error.";
      }
    }
  }

  // Check if $uploadOk is set to 0 by an error
  if ($uploadOk == 0) {
    $message .= " Sorry, your file was not uploaded.";
  // if everything is ok, try to upload file
  }
  
  if (isset($_POST['getCountyNames']) && $_POST['getCountyNames'] == 'yes') {
      $getCountyNames = TRUE;
  } else {
      $getCountyNames = FALSE;
  }

  $geoHttpContext = null;
  if ($getCountyNames) {
    $geoHttpContext = stream_context_create(array(
      'http' => array(
        'timeout' => 3,
        'ignore_errors' => true,
      )
    ));
  }

  // Successful? Go ahead and process it
  if ($uploadOk == 1) {
    $tableId = preg_replace('/[^A-Za-z0-9_.-]/', '_', $fileBase) . '.csv';
    $message = h($fileName) . ' ('. (int)$fileSize . ' bytes ) processed. <a href="#bottom">Jump to the bottom</a>' . "\n";
    echo '    <div class="alert alert-success" role="alert">' . $message . "</div>\n";
    echo '    <table class="table table-striped" id="'. h($tableId) . '">
        <thead>
          <tr>
            <th scope="col">Code</th>
            <th scope="col">Name</th>
            <th scope="col">Latitude/Longitude</th>
            <th scope="col">Size</th>
            <th scope="col">D/T</th>
            <th scope="col">Hint</th>
            <th scope="col">Logs</th>
          </tr>
        </thead>
        <tbody>' . "\n";

  function DECtoDMS($latitude, $longitude) {
    $latitude = (float)$latitude;
    $longitude = (float)$longitude;

    $latitudeDirection = $latitude < 0 ? 'S' : 'N';
    $longitudeDirection = $longitude < 0 ? 'W' : 'E';

    $latitudeAbs = abs($latitude);
    $longitudeAbs = abs($longitude);

    $latitudeInDegrees = (int) floor($latitudeAbs);
    $longitudeInDegrees = (int) floor($longitudeAbs);

    $latitudeMinutes = ($latitudeAbs - $latitudeInDegrees) * 60;
    $longitudeMinutes = ($longitudeAbs - $longitudeInDegrees) * 60;

    return sprintf('%s %d° %06.3f %s %d° %06.3f',
        $latitudeDirection,
        $latitudeInDegrees,
        $latitudeMinutes,
        $longitudeDirection,
        $longitudeInDegrees,
        $longitudeMinutes
    );
  }

  libxml_use_internal_errors(true);
  $xml = simplexml_load_file($fileSource, 'SimpleXMLElement', LIBXML_NONET);
  if ($xml === false) {
    echo '    <div class="alert alert-danger" role="alert">Unable to parse GPX data.</div>' . "\n";
    $uploadOk = 0;
  }
  libxml_clear_errors();

  if ($uploadOk == 1) {
  foreach ($xml->wpt as $wpt) {
    $cacheGC = $wpt->name;       // GC code
    $cacheURL = $wpt->url;       // Link to the GC on geocaching.com
    $cachename = $wpt->urlname;  // Name
    $cachelat = (float)$wpt['lat'];     // Latitude (30.759283)
    $cachelon = (float)$wpt['lon'];     // Longitude (-98.676233)
    $cachelatlon = DECtoDMS($cachelat, $cachelon);
    $cacheMapQuery = rawurlencode((string)$cachelat . ',' . (string)$cachelon);
    list($cachetype) = explode("|", $wpt->type);  // Geocache|Traditional Cache Waypoint|Final Location
    $cacheinfo = $wpt->children('http://www.groundspeak.com/cache/1/0/1');
    $cachecontainer = $cacheinfo->cache->container;    // "Small"
    $cachedifficulty = $cacheinfo->cache->difficulty;  // Difficulty rating, "1"
    $cacheterrain = $cacheinfo->cache->terrain;        // Terrain "1.5"
    $cachehint = $cacheinfo->cache->encoded_hints;     // Hint (not encrypted)
    $cacheURLSafe = filter_var((string)$cacheURL, FILTER_VALIDATE_URL) ? (string)$cacheURL : '#';

    if ($getCountyNames) {
    // Get region (state, county) information from Geonames
        $geonamesURL = 'http://api.geonames.org/findNearbyPlaceName?';
        $param = http_build_query(array(
            'lat' => (string)$cachelat,
            'lng' => (string)$cachelon,
            'style' => 'FULL',
            'username' => 'mymaestro')
        );
        $url = $geonamesURL . $param;
        $geonamesResponse = @file_get_contents($url, false, $geoHttpContext);
        $geonames = ($geonamesResponse !== false) ? @simplexml_load_string($geonamesResponse) : false;
        $geoState = '';
        $geoCounty = '';
        if ($geonames !== false && isset($geonames->geoname)) {
          $geoState = (string)$geonames->geoname->adminName1;     // State: "Texas"
          $geoCounty = (string)$geonames->geoname->adminName2;    // County: "Bell"
        }
    }
    // Only care if Geocache (not Waypoints)
    if ($cachetype == "Geocache" ) {
      echo "          <tr>\n";
      // echo '        <th scope="row"><a href="'.$cacheURL.'">'.$cacheGC."</a></th>\n";
      echo '            <td><a href="'.h($cacheURLSafe).'" target="_blank" rel="noopener noreferrer">'.h($cacheGC)."</a></td>\n";
      echo '            <td>'.h($cachename)."</td>\n";
      //echo '            <td>'.$cachelatlon.'<br><a href="https://nominatim.openstreetmap.org/search.php?q='.$cachelatlon.'&polygon_geojson=1&viewbox=">Map</a>'."</td>\n";
      echo '            <td><a href="https://nominatim.openstreetmap.org/search.php?q='.$cacheMapQuery.'&polygon_geojson=1&viewbox=" target="_blank" rel="noopener noreferrer">'.h($cachelatlon)."</a>\n";
      if ($getCountyNames && ($geoCounty !== '' || $geoState !== '')) {
        echo "<br>".h($geoCounty) . " county, ". h($geoState);
      }
      echo "</td>\n";
      echo '            <td>'.h($cachecontainer)."</td>\n";
      echo '            <td>'.h($cachedifficulty).' / '.h($cacheterrain)."</td>\n";
      echo '            <td>'.h($cachehint)."</td>\n";
      echo '            <td>';

      $cachelogs = $cacheinfo->cache->logs;  // Last 5 cache logs
      $lastfoundepoch = 0;                   // Keep the most recent log's date

      $findlog = array();                    // An array to keep relevant log messages
      $findlog[] = "Not found recently";
      foreach ($cachelogs->log as $logs) {
        $cachelogdatetime = $logs->date;
        $cachelogepoch = strtotime($cachelogdatetime);
        $parsedate = date_parse($cachelogdatetime);
        $cachelogdate = $parsedate["month"] . "-" . $parsedate["day"] . "-" . $parsedate["year"];
        $cachelogtype = $logs->type;
        $cachelogtext = str_replace('"', "'", $logs->text);
        $noDNF = TRUE;                       // If there are no DNF's only keep the most recent find

        switch ($cachelogtype) {
          case "Found it":
            $findlog[] = "Found on " . $cachelogdate;
            if ($cachelogepoch  > $lastfoundepoch ) {
              $lastfoundepoch = $cachelogepoch;
              $findlog[0] = "Last found on " . date('m-d-Y', $cachelogepoch);
            }
            break;
          case "Didn't find it":
            $findlog[] =  "DNF on " . $cachelogdate;
            $noDNF = FALSE;
            break;
          case "Publish Listing":
            $findlog[] =  "Published on " . $cachelogdate . ": " . $cachelogtext;
            $noDNF = FALSE;
            break;
          case "Archive":
            $findlog[] =  "Archived on " . $cachelogdate . ": " . $cachelogtext;
            $noDNF = FALSE;
            break;
          case "Unarchive":
            $findlog[] =  "Unarchived on " . $cachelogdate . ": " . $cachelogtext;
            $noDNF = FALSE;
            break;
          case "Owner Maintenance":
            $findlog[] =  "Maintained on " . $cachelogdate . ": " . $cachelogtext;
            $noDNF = FALSE;
            break;
          case "Write note":
          case "Post Reviewer Note":
            $findlog[] =  "Note on " . $cachelogdate . ": " . $cachelogtext;
            $noDNF = FALSE;
            break;
          default:
            $findlog[] =  "Unknown log type on " . $cachelogdate . ": " . $cachelogtext;
            $noDNF = FALSE;
        }
      }
      if ($noDNF == TRUE) {
        echo h($findlog[0]);
      } else {
        foreach($findlog as $note) {
          echo h($note) . "<br>";
        }
      }
      echo "</td>\n";
      echo "          </tr>\n";
    }
  }
  }
    echo '        </tbody>
      </table>
      <a id="bottom"></a>
    ';

    // Delete the GPX file!
    if (!empty($fileZip) && file_exists($fileZip)) {
      unlink($fileZip);
    }
    if (!empty($fileSource) && file_exists($fileSource)) {
      unlink($fileSource);
    }

  } else { // File not OK to upload
    echo '    <div class="alert alert-danger" role="alert">' . $message . "</div>\n";
  }

} else { // Not processing an upload, show the form
  echo '
  <div class="headline">
      <form action="gpx2csv.php" method="post" enctype="multipart/form-data" id="form1" class="mx-auto" style="max-width: 680px;">
        <input type="hidden" name="submit" value="Upload">
        <input type="file" name="fileToUpload" id="fileToUpload" class="d-none" accept=".gpx,.zip">
        <div id="dropZone" class="dropzone border border-secondary rounded p-4 bg-light" role="button" tabindex="0" aria-label="Upload GPX or zip file">
          <div class="h5 mb-2">Drop GPX/ZIP file here</div>
          <div class="text-muted mb-0">or click to choose a file</div>
          <div id="selectedFileName" class="small mt-3 text-dark"></div>
          <div id="uploadingStatus" class="small mt-2 text-primary d-none">Uploading...</div>
        </div>
        <div class="form-check mt-3 text-left">
          <input class="form-check-input" type="checkbox" name="getCountyNames" id="getCountyNames" value="yes">
          <label class="form-check-label" for="getCountyNames">Get county names (takes way longer!)</label>
        </div>
      </form>
    </div>
  ';
}
error_log($message);
?>
  </div><!-- class container-->
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
    var $form = $('#form1');
    var $fileInput = $('#fileToUpload');
    var $dropZone = $('#dropZone');
    var $selectedFileName = $('#selectedFileName');
    var $uploadingStatus = $('#uploadingStatus');
    var isSubmitting = false;

    if (!$form.length || !$fileInput.length || !$dropZone.length) {
      return;
    }

    function showSelectedFile(file) {
      if (file && file.name) {
        $selectedFileName.text('Selected: ' + file.name);
      }
    }

    function submitWhenReady() {
      if (isSubmitting) {
        return;
      }

      if ($fileInput[0].files && $fileInput[0].files.length > 0) {
        isSubmitting = true;
        $dropZone.addClass('dropzone-uploading');
        if ($uploadingStatus.length) {
          $uploadingStatus.removeClass('d-none');
        }
        if (typeof $form[0].requestSubmit === 'function') {
          $form[0].requestSubmit();
        } else {
          $form[0].submit();
        }
      }
    }

    $dropZone.on('click', function () {
      if (isSubmitting) {
        return;
      }
      $fileInput.trigger('click');
    });

    $dropZone.on('keydown', function (event) {
      if (isSubmitting) {
        return;
      }
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        $fileInput.trigger('click');
      }
    });

    $fileInput.on('change', function () {
      var file = this.files && this.files.length ? this.files[0] : null;
      showSelectedFile(file);
      submitWhenReady();
    });

    $dropZone.on('dragenter dragover', function (event) {
      if (isSubmitting) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      $dropZone.addClass('dropzone-active border-primary');
    });

    $dropZone.on('dragleave dragend drop', function (event) {
      if (isSubmitting) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      $dropZone.removeClass('dropzone-active border-primary');
    });

    $dropZone.on('drop', function (event) {
      if (isSubmitting) {
        return;
      }
      var files = event.originalEvent.dataTransfer ? event.originalEvent.dataTransfer.files : null;
      if (!files || !files.length) {
        return;
      }

      var dataTransfer = new DataTransfer();
      dataTransfer.items.add(files[0]);
      $fileInput[0].files = dataTransfer.files;
      showSelectedFile(files[0]);
      submitWhenReady();
    });
  });
  </script>
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
      window.location.href = 'gpx2csv.php';
    });

    $scrollTopButton.on('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
