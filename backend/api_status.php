<?php
error_reporting(E_ERROR | E_PARSE);

/**
 * backend/api_status.php — Public Store Info Endpoint
 *
 * Returns tagline and heading text. No authentication required.
 *
 * GET /api_status.php
 *
 * Response:
 * {
 *   "tagline": "...",
 *   "heading": { "prefix": "...", "brand": "..." },
 *   "timestamp": "2026-07-19T..."
 * }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Jakarta');

$response = [
    'tagline'   => loadTagline(),
    'heading'   => loadHeading(),
    'timestamp' => date('c'),
];

echo json_encode($response, JSON_PRETTY_PRINT);
