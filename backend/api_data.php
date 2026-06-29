<?php
/**
 * backend/api_data.php — Data File Proxy with CORS
 *
 * Serves JSON/text data files from the data/ directory with proper CORS headers.
 * This allows GitHub Pages (and other cross-origin clients) to access these files
 * without being blocked by CORS policy (since Render serves them as static files
 * without CORS headers).
 *
 * Usage:
 *   api_data.php?file=product_info.json
 *   api_data.php?file=heading.json
 *   api_data.php?file=cache_produk.json
 *   api_data.php?file=jam_operasional.json
 *   api_data.php?file=jadwal_tutup.json
 *   api_data.php?file=status_toko.txt
 *   api_data.php?file=tagline.json
 *   api_data.php?file=banners.json
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cors.php';
handleCORS();

$file = basename($_GET['file'] ?? '');

// Whitelist — only these files are allowed (security measure)
$allowed = [
    'product_info.json',
    'heading.json',
    'tagline.json',
    'cache_produk.json',
    'jam_operasional.json',
    'jadwal_tutup.json',
    'banners.json',
    'status_toko.txt',
];

if (!in_array($file, $allowed)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$path = __DIR__ . '/data/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Set correct Content-Type for plain text files
if (pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo file_get_contents($path);
