<?php
require_once __DIR__ . '/includes/layout.php';

$extraHeadHtml = <<<'HTML'
  <script src="files/table2CSV.js"></script>
  <script>
  $(document).ready(function () {
    $('table').each(function () {
      var $table = $(this);
      var tableId = $table.attr('id') || 'geocaches.csv';
      var baseName = tableId.replace(/\.csv$/i, '');

      var $buttonRow = $('<div class="mt-3 mb-2">');
      var $csvButton = $('<button type="button" class="btn btn-primary me-2">Export CSV</button>');
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
HTML;

renderPageStart(array(
  'title' => 'Geocaching GPX to CSV',
  'description' => 'CSV from Geocaching GPX',
  'activeNav' => 'gpx2csv',
  'extraHeadHtml' => $extraHeadHtml,
));
?>
    <div class="headline">
      <img src="images/gpx.png"><img src="images/circle-right.png"><img src="images/csv.png">
      <h1>Create a spreadsheet from a Geocaching list</h1>
      <p class="lead">Take a GPX file from a Geocaching List or Pocket Query<br>Get a table with critical information.</p>
    </div>
<?php
$message = '';
require_once __DIR__ . '/includes/gpx_helpers.php';
require_once __DIR__ . '/includes/gpx_format_helpers.php';
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
        <div class="form-check mt-3 text-start">
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
<?php renderPageEnd(array('includeFloatingButtons' => true, 'clearPageHref' => 'gpx2csv.php')); ?>
