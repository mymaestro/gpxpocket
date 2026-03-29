<?php

// Storage helpers for anonymous profile datasets.
// Data is persisted server-side and keyed by profileId derived from the
// profile token cookie.

if (!function_exists('profileStorageDefaultBaseDir')) {
    function profileStorageDefaultBaseDir() {
        return dirname(__DIR__) . '/data/profiles';
    }
}

if (!function_exists('profileStorageIsValidProfileId')) {
    function profileStorageIsValidProfileId($profileId) {
        return (bool)preg_match('/^[a-f0-9]{32}$/', trim((string)$profileId));
    }
}

if (!function_exists('profileStorageNowIsoUtc')) {
    function profileStorageNowIsoUtc() {
        return gmdate('c');
    }
}

if (!function_exists('profileStorageEnsureDir')) {
    function profileStorageEnsureDir($dirPath, &$error) {
        if (is_dir($dirPath)) {
            return true;
        }

        if (@mkdir($dirPath, 0700, true)) {
            return true;
        }

        clearstatcache(true, $dirPath);
        if (is_dir($dirPath)) {
            return true;
        }

        $error = 'Unable to create profile storage directory: ' . $dirPath;
        return false;
    }
}

if (!function_exists('profileStorageShardDir')) {
    function profileStorageShardDir($profileId, $baseDir = null) {
        $profileId = trim((string)$profileId);
        $base = ($baseDir === null || trim((string)$baseDir) === '')
            ? profileStorageDefaultBaseDir()
            : rtrim((string)$baseDir, '/');

        return $base . '/' . substr($profileId, 0, 2);
    }
}

if (!function_exists('profileStorageDataPath')) {
    function profileStorageDataPath($profileId, $baseDir = null) {
        return profileStorageShardDir($profileId, $baseDir) . '/' . $profileId . '.json';
    }
}

if (!function_exists('profileStorageWriteJsonAtomic')) {
    function profileStorageWriteJsonAtomic($targetPath, $payload, &$error) {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            $error = 'Failed to encode profile storage payload.';
            return false;
        }

        $targetDir = dirname($targetPath);
        $tempPath = tempnam($targetDir, 'profile_tmp_');
        if ($tempPath === false) {
            $error = 'Unable to create temporary profile storage file.';
            return false;
        }

        $bytes = @file_put_contents($tempPath, $json, LOCK_EX);
        if ($bytes === false) {
            @unlink($tempPath);
            $error = 'Unable to write temporary profile storage file.';
            return false;
        }

        if (!@rename($tempPath, $targetPath)) {
            @unlink($tempPath);
            $error = 'Unable to finalize profile storage write.';
            return false;
        }

        @chmod($targetPath, 0600);
        return true;
    }
}

if (!function_exists('profileStorageLoad')) {
    function profileStorageLoad($profileId, $baseDir = null) {
        if (!profileStorageIsValidProfileId($profileId)) {
            return array(
                'ok' => false,
                'exists' => false,
                'error' => 'Invalid profile ID.',
                'payload' => array(),
                'meta' => array(),
                'findsByCode' => array(),
            );
        }

        $path = profileStorageDataPath($profileId, $baseDir);
        if (!file_exists($path)) {
            return array(
                'ok' => true,
                'exists' => false,
                'error' => '',
                'payload' => array(),
                'meta' => array(),
                'findsByCode' => array(),
            );
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return array(
                'ok' => false,
                'exists' => true,
                'error' => 'Unable to read profile storage file.',
                'payload' => array(),
                'meta' => array(),
                'findsByCode' => array(),
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array(
                'ok' => false,
                'exists' => true,
                'error' => 'Profile storage data is not valid JSON.',
                'payload' => array(),
                'meta' => array(),
                'findsByCode' => array(),
            );
        }

        $findsByCode = (isset($decoded['findsByCode']) && is_array($decoded['findsByCode']))
            ? $decoded['findsByCode']
            : array();

        $meta = array(
            'version' => isset($decoded['version']) ? (int)$decoded['version'] : 1,
            'profileId' => isset($decoded['profileId']) ? (string)$decoded['profileId'] : '',
            'datasetType' => isset($decoded['datasetType']) ? (string)$decoded['datasetType'] : '',
            'finderName' => isset($decoded['finderName']) ? (string)$decoded['finderName'] : '',
            'finderId' => isset($decoded['finderId']) ? (string)$decoded['finderId'] : '',
            'createdAt' => isset($decoded['createdAt']) ? (string)$decoded['createdAt'] : '',
            'updatedAt' => isset($decoded['updatedAt']) ? (string)$decoded['updatedAt'] : '',
            'lastAccessedAt' => isset($decoded['lastAccessedAt']) ? (string)$decoded['lastAccessedAt'] : '',
            'findCount' => isset($decoded['findCount']) ? (int)$decoded['findCount'] : count($findsByCode),
            'sourceLabel' => isset($decoded['sourceLabel']) ? (string)$decoded['sourceLabel'] : '',
        );

        return array(
            'ok' => true,
            'exists' => true,
            'error' => '',
            'payload' => $decoded,
            'meta' => $meta,
            'findsByCode' => $findsByCode,
        );
    }
}

if (!function_exists('profileStorageSaveMyFinds')) {
    function profileStorageSaveMyFinds($profileId, $findsByCode, $options = array(), $baseDir = null) {
        if (!profileStorageIsValidProfileId($profileId)) {
            return array('ok' => false, 'error' => 'Invalid profile ID.', 'path' => '');
        }
        if (!is_array($findsByCode)) {
            return array('ok' => false, 'error' => 'findsByCode must be an array.', 'path' => '');
        }

        $error = '';
        $dirPath = profileStorageShardDir($profileId, $baseDir);
        if (!profileStorageEnsureDir($dirPath, $error)) {
            return array('ok' => false, 'error' => $error, 'path' => '');
        }

        $path = profileStorageDataPath($profileId, $baseDir);
        $now = profileStorageNowIsoUtc();

        $existing = profileStorageLoad($profileId, $baseDir);
        $createdAt = $now;
        if (!empty($existing['ok']) && !empty($existing['exists']) && !empty($existing['meta']['createdAt'])) {
            $createdAt = (string)$existing['meta']['createdAt'];
        }

        $payload = array(
            'version' => 1,
            'profileId' => $profileId,
            'datasetType' => 'my-finds',
            'finderName' => isset($options['finderName']) ? trim((string)$options['finderName']) : '',
            'finderId' => isset($options['finderId']) ? trim((string)$options['finderId']) : '',
            'sourceLabel' => isset($options['sourceLabel']) ? trim((string)$options['sourceLabel']) : 'My Finds PQ upload',
            'createdAt' => $createdAt,
            'updatedAt' => $now,
            'lastAccessedAt' => $now,
            'findCount' => count($findsByCode),
            'findsByCode' => $findsByCode,
        );

        if (!profileStorageWriteJsonAtomic($path, $payload, $error)) {
            return array('ok' => false, 'error' => $error, 'path' => $path);
        }

        return array('ok' => true, 'error' => '', 'path' => $path, 'meta' => array(
            'profileId' => $profileId,
            'findCount' => count($findsByCode),
            'updatedAt' => $now,
        ));
    }
}

if (!function_exists('profileStorageTouchAccessed')) {
    function profileStorageTouchAccessed($profileId, $baseDir = null) {
        $loaded = profileStorageLoad($profileId, $baseDir);
        if (empty($loaded['ok']) || empty($loaded['exists'])) {
            return $loaded;
        }

        $payload = $loaded['payload'];
        $payload['lastAccessedAt'] = profileStorageNowIsoUtc();

        $error = '';
        $path = profileStorageDataPath($profileId, $baseDir);
        if (!profileStorageWriteJsonAtomic($path, $payload, $error)) {
            return array('ok' => false, 'error' => $error, 'path' => $path);
        }

        return array('ok' => true, 'error' => '', 'path' => $path);
    }
}

if (!function_exists('profileStorageDelete')) {
    function profileStorageDelete($profileId, $baseDir = null) {
        if (!profileStorageIsValidProfileId($profileId)) {
            return array('ok' => false, 'error' => 'Invalid profile ID.', 'path' => '');
        }

        $path = profileStorageDataPath($profileId, $baseDir);
        if (!file_exists($path)) {
            return array('ok' => true, 'error' => '', 'path' => $path, 'deleted' => false);
        }

        if (!@unlink($path)) {
            return array('ok' => false, 'error' => 'Unable to delete profile data file.', 'path' => $path, 'deleted' => false);
        }

        return array('ok' => true, 'error' => '', 'path' => $path, 'deleted' => true);
    }
}

if (!function_exists('profileStoragePruneExpired')) {
    function profileStoragePruneExpired($maxAgeSeconds = null, $baseDir = null) {
        $base = ($baseDir === null || trim((string)$baseDir) === '')
            ? profileStorageDefaultBaseDir()
            : rtrim((string)$baseDir, '/');
        $ttl = ($maxAgeSeconds === null) ? (60 * 60 * 24 * 90) : max(3600, (int)$maxAgeSeconds);

        $result = array(
            'ok' => true,
            'error' => '',
            'deleted' => 0,
            'scanned' => 0,
        );

        if (!is_dir($base)) {
            return $result;
        }

        $cutoffTs = time() - $ttl;
        $dirs = @scandir($base);
        if (!is_array($dirs)) {
            return array('ok' => false, 'error' => 'Unable to scan profile storage directory.', 'deleted' => 0, 'scanned' => 0);
        }

        foreach ($dirs as $dirName) {
            if ($dirName === '.' || $dirName === '..') {
                continue;
            }

            $shardPath = $base . '/' . $dirName;
            if (!is_dir($shardPath)) {
                continue;
            }

            $files = glob($shardPath . '/*.json');
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $filePath) {
                $result['scanned']++;
                $raw = @file_get_contents($filePath);
                if ($raw === false || trim($raw) === '') {
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    continue;
                }

                $lastAccessedAt = isset($decoded['lastAccessedAt']) ? (string)$decoded['lastAccessedAt'] : '';
                $updatedAt = isset($decoded['updatedAt']) ? (string)$decoded['updatedAt'] : '';
                $referenceTs = strtotime($lastAccessedAt !== '' ? $lastAccessedAt : $updatedAt);
                if ($referenceTs === false) {
                    continue;
                }

                if ($referenceTs < $cutoffTs && @unlink($filePath)) {
                    $result['deleted']++;
                }
            }
        }

        return $result;
    }
}
