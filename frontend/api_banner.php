<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = __DIR__ . '/../backend/data/banners.json';
if (!file_exists($file)) {
    echo '[]';
    exit;
}
echo file_get_contents($file);
