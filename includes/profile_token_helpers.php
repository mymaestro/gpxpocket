<?php

// Cookie + token helpers for anonymous persistent profile storage.
// This avoids user accounts while still isolating one user's stored finds
// from another user's session.

if (!function_exists('profileTokenCookieName')) {
    function profileTokenCookieName() {
        return 'gpxpocket_profile_token';
    }
}

if (!function_exists('profileTokenTtlSeconds')) {
    function profileTokenTtlSeconds() {
        return 60 * 60 * 24 * 90; // 90 days
    }
}

if (!function_exists('profileTokenGenerate')) {
    function profileTokenGenerate($byteLength = 32) {
        $byteLength = (int)$byteLength;
        if ($byteLength < 16) {
            $byteLength = 16;
        }
        return bin2hex(random_bytes($byteLength));
    }
}

if (!function_exists('profileTokenIsValid')) {
    function profileTokenIsValid($token) {
        $token = trim((string)$token);
        if ($token === '') {
            return false;
        }
        if ((strlen($token) % 2) !== 0) {
            return false;
        }
        return (bool)preg_match('/^[a-f0-9]{32,128}$/', $token);
    }
}

if (!function_exists('profileRequestIsHttps')) {
    function profileRequestIsHttps() {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
        }
        return false;
    }
}

if (!function_exists('profileSetTokenCookie')) {
    function profileSetTokenCookie($token, $ttlSeconds = null) {
        if (!profileTokenIsValid($token)) {
            return false;
        }

        $ttl = ($ttlSeconds === null) ? profileTokenTtlSeconds() : max(300, (int)$ttlSeconds);
        $expiresAt = time() + $ttl;

        return setcookie(profileTokenCookieName(), $token, array(
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => profileRequestIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }
}

if (!function_exists('profileGetTokenFromCookie')) {
    function profileGetTokenFromCookie() {
        $cookieName = profileTokenCookieName();
        if (!isset($_COOKIE[$cookieName])) {
            return '';
        }
        $token = trim((string)$_COOKIE[$cookieName]);
        return profileTokenIsValid($token) ? $token : '';
    }
}

if (!function_exists('profileRotateTokenCookie')) {
    function profileRotateTokenCookie($ttlSeconds = null) {
        $token = profileTokenGenerate(32);
        if (!profileSetTokenCookie($token, $ttlSeconds)) {
            return '';
        }
        return $token;
    }
}

if (!function_exists('profileSigningKeyFromEnv')) {
    function profileSigningKeyFromEnv() {
        $envKey = getenv('GPXPOCKET_PROFILE_TOKEN_KEY');
        if (!is_string($envKey)) {
            return '';
        }
        $envKey = trim($envKey);
        return ($envKey !== '') ? $envKey : '';
    }
}

if (!function_exists('profileTokenHash')) {
    function profileTokenHash($token) {
        $token = trim((string)$token);
        if (!profileTokenIsValid($token)) {
            return '';
        }

        $signingKey = profileSigningKeyFromEnv();
        if ($signingKey === '') {
            // Fallback keeps local dev simple. For production set
            // GPXPOCKET_PROFILE_TOKEN_KEY so profile IDs are keyed/hardened.
            return hash('sha256', $token);
        }

        return hash_hmac('sha256', $token, $signingKey);
    }
}

if (!function_exists('profileIdFromToken')) {
    function profileIdFromToken($token) {
        $hash = profileTokenHash($token);
        if ($hash === '') {
            return '';
        }

        // Keep IDs compact but collision-resistant for practical scale.
        return substr($hash, 0, 32);
    }
}

if (!function_exists('profileGetOrCreateTokenContext')) {
    function profileGetOrCreateTokenContext() {
        $token = profileGetTokenFromCookie();
        $isNewToken = false;

        if ($token === '') {
            $token = profileRotateTokenCookie();
            if ($token === '') {
                return array(
                    'ok' => false,
                    'error' => 'Unable to set profile token cookie.',
                    'token' => '',
                    'profileId' => '',
                    'isNewToken' => false,
                );
            }
            $isNewToken = true;
        }

        $profileId = profileIdFromToken($token);
        if ($profileId === '') {
            return array(
                'ok' => false,
                'error' => 'Unable to derive profile ID from token.',
                'token' => '',
                'profileId' => '',
                'isNewToken' => $isNewToken,
            );
        }

        return array(
            'ok' => true,
            'error' => '',
            'token' => $token,
            'profileId' => $profileId,
            'isNewToken' => $isNewToken,
        );
    }
}
