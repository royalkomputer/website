<?php
/**
 * backend/cors.php — CORS Helper
 *
 * Include this file in any public API endpoint that needs CORS support.
 * Validates the Origin header against an allowed list, sets the
 * appropriate Access-Control headers, and handles OPTIONS preflight.
 *
 * Usage:
 *   require_once __DIR__ . '/cors.php';
 *   handleCORS();
 *
 * Allowed origins:
 *   - http://localhost:5173        (Vite dev server)
 *   - http://localhost:8080        (PHP fallback dev server)
 *   - https://royal-komputer.netlify.app  (Netlify production)
 *   - https://royal-backend-s3ir.onrender.com  (Render production)
 */

/**
 * Set CORS headers and handle OPTIONS preflight.
 * Call this at the top of any public API endpoint.
 */
function handleCORS(): void {
    $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:8080',
        'https://royal-komputer.netlify.app',
        'https://royal-backend-s3ir.onrender.com',
    ];

    // Determine the origin to allow
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins)) {
        // Known origin — echo it back with credentials support
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    } else {
        // Unknown or no origin — use wildcard (safe for public APIs, no credentials)
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
