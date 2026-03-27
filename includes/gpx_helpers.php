<?php

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('endswith')) {
    function endswith($haystack, $needle) {
        $strlen = strlen($haystack);
        $testlen = strlen($needle);
        if ($testlen > $strlen) return false;
        return substr_compare($haystack, $needle, $strlen - $testlen, $testlen) === 0;
    }
}

if (!function_exists('normalizeUploadArray')) {
    function normalizeUploadArray($fileField) {
        if (!isset($fileField['name'])) {
            return array();
        }

        if (!is_array($fileField['name'])) {
            return array($fileField);
        }

        $normalized = array();
        $count = count($fileField['name']);
        for ($index = 0; $index < $count; $index++) {
            $normalized[] = array(
                'name' => $fileField['name'][$index],
                'type' => $fileField['type'][$index],
                'tmp_name' => $fileField['tmp_name'][$index],
                'error' => $fileField['error'][$index],
                'size' => $fileField['size'][$index],
            );
        }
        return $normalized;
    }
}

if (!function_exists('formatUploadBytesLabel')) {
    function formatUploadBytesLabel($bytes) {
        $bytes = (int)$bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = array('B', 'KB', 'MB', 'GB');
        $value = (float)$bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, $unitIndex === 0 ? 0 : 1) . ' ' . $units[$unitIndex];
    }
}

if (!function_exists('extractGpxFromUpload')) {
    function extractGpxFromUpload($upload, $maxUploadBytes, &$message) {
        $fileName = basename(isset($upload['name']) ? (string)$upload['name'] : 'uploaded file');
        $uploadError = isset($upload['error']) ? (int)$upload['error'] : UPLOAD_ERR_NO_FILE;

        if ($uploadError !== UPLOAD_ERR_OK) {
            switch ($uploadError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $message .= 'Upload too large for ' . $fileName . '. Increase PHP upload limits and try again. ';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message .= 'Upload was incomplete for ' . $fileName . '. Please retry. ';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message .= 'No file uploaded for ' . $fileName . '. ';
                    break;
                default:
                    $message .= 'Upload failed for ' . $fileName . ' (error code ' . $uploadError . '). ';
                    break;
            }
            return null;
        }

        $fileSource = $upload['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = (int)$upload['size'];
        $fileZip = '';

        if (!is_uploaded_file($fileSource)) {
            $message .= 'Invalid upload source for ' . $fileName . '. ';
            return null;
        }

        if ($fileSize <= 0 || $fileSize > $maxUploadBytes) {
            $message .= 'Invalid file size for ' . $fileName . ' (max ' . formatUploadBytesLabel($maxUploadBytes) . '). ';
            return null;
        }

        if ($fileExt !== 'gpx') {
            if ($fileExt !== 'zip') {
                $message .= 'Only GPX/ZIP supported: ' . $fileName . '. ';
                return null;
            }

            $filePath = pathinfo(realpath($fileSource), PATHINFO_DIRNAME);
            $zip = new ZipArchive;
            $foundGpx = false;

            if ($zip->open($fileSource) !== true) {
                $message .= 'Failed to open ZIP: ' . $fileName . '. ';
                return null;
            }

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
                    $zipStream = $zip->getStream($zfileName);
                    if ($zipStream !== false) {
                        $tmpExtracted = tempnam($filePath, 'gpx_');
                        if ($tmpExtracted !== false) {
                            $outHandle = fopen($tmpExtracted, 'wb');
                            if ($outHandle !== false) {
                                $streamOk = true;
                                while (!feof($zipStream)) {
                                    $chunk = fread($zipStream, 1024 * 1024);
                                    if ($chunk === false) {
                                        $streamOk = false;
                                        break;
                                    }
                                    if ($chunk === '') {
                                        continue;
                                    }
                                    if (fwrite($outHandle, $chunk) === false) {
                                        $streamOk = false;
                                        break;
                                    }
                                }

                                fclose($outHandle);
                                fclose($zipStream);

                                if ($streamOk) {
                                    $fileZip = $fileSource;
                                    $fileSource = $tmpExtracted;
                                    $foundGpx = true;
                                    break;
                                }

                                if (file_exists($tmpExtracted)) {
                                    unlink($tmpExtracted);
                                }
                            } else {
                                fclose($zipStream);
                                if (file_exists($tmpExtracted)) {
                                    unlink($tmpExtracted);
                                }
                            }
                        } else {
                            fclose($zipStream);
                        }
                    }
                }
            }
            $zip->close();

            if (!$foundGpx) {
                $message .= 'No GPX found inside ZIP: ' . $fileName . '. ';
                return null;
            }
        }

        return array(
            'source' => $fileSource,
            'zip' => $fileZip,
            'name' => $fileName,
        );
    }
}

if (!function_exists('cleanupExtracted')) {
    function cleanupExtracted($parsedUpload) {
        if (!$parsedUpload) {
            return;
        }
        if (!empty($parsedUpload['zip']) && file_exists($parsedUpload['zip'])) {
            unlink($parsedUpload['zip']);
        }
        if (!empty($parsedUpload['source']) && file_exists($parsedUpload['source'])) {
            unlink($parsedUpload['source']);
        }
    }
}

if (!function_exists('getLogIdFromNode')) {
    function getLogIdFromNode($logNode) {
        if (!$logNode) {
            return '';
        }

        $idFromArray = trim((string)$logNode['id']);
        if ($idFromArray !== '') {
            return $idFromArray;
        }

        $attrs = $logNode->attributes();
        if ($attrs !== null && isset($attrs['id'])) {
            $idFromAttrs = trim((string)$attrs['id']);
            if ($idFromAttrs !== '') {
                return $idFromAttrs;
            }
        }

        return '';
    }
}
