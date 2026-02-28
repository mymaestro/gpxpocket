<?php

if (!function_exists('DECtoDMS')) {
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
}

if (!function_exists('formatSnapshotDate')) {
    function formatSnapshotDate($timestamp) {
      if (!is_numeric($timestamp) || (int)$timestamp <= 0) {
        return 'Unknown';
      }

      return date('M j, Y g:i A T', (int)$timestamp);
    }
}

if (!function_exists('formatLogDate')) {
    function formatLogDate($dateValue) {
      $timestamp = strtotime((string)$dateValue);
      if ($timestamp === false) {
        return (string)$dateValue;
      }

      return date('M j, Y g:i A T', $timestamp);
    }
}

if (!function_exists('formatDisplayDate')) {
    function formatDisplayDate($rawDate, $timestamp) {
      if (is_numeric($timestamp) && (int)$timestamp > 0) {
        return date('M j, Y g:i A T', (int)$timestamp);
      }

      $parsed = strtotime((string)$rawDate);
      if ($parsed !== false) {
        return date('M j, Y g:i A T', $parsed);
      }

      return (string)$rawDate;
    }
}
