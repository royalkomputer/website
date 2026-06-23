<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$script = realpath(__DIR__ . '/../sync/update_produk.php');

if (!$script || !file_exists($script)) {
    echo json_encode(['success' => false, 'output' => 'File sync/update_produk.php tidak ditemukan']);
    exit;
}

$output = [];
$return_var = 0;
$cmd = 'php ' . escapeshellarg($script) . ' 2>&1';
exec($cmd, $output, $return_var);

$output_text = implode("\n", $output);

echo json_encode([
    'success' => $return_var === 0,
    'output' => $output_text,
    'exit_code' => $return_var,
]);
