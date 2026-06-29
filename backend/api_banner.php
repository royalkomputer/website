<?php
header('Content-Type: application/json');
require_once __DIR__ . '/cors.php';
handleCORS();

$file = __DIR__ . '/data/banners.json';
if (!file_exists($file)) {
    echo '[]';
    exit;
}
echo file_get_contents($file);
