<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: text/plain; charset=utf-8');

// --- Load .env ---
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        putenv($line);
    }
}

$secret = getenv('GITHUB_WEBHOOK_SECRET') ?: '';
if (!$secret) {
    http_response_code(500);
    echo 'Webhook secret not configured. Set GITHUB_WEBHOOK_SECRET in .env';
    exit;
}

// --- Validasi signature HMAC-SHA256 ---
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $sig)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

// --- Eksekusi git pull ---
chdir(__DIR__);
$output = [];
$return = -1;
exec('git fetch origin main 2>&1 && git reset --hard origin/main 2>&1', $output, $return);

$log = date('Y-m-d H:i:s') . ' | exit:' . $return . ' | ' . implode('; ', $output);
@file_put_contents(__DIR__ . '/data/webhook.log', $log . PHP_EOL, FILE_APPEND);

if ($return === 0) {
    echo 'OK';
} else {
    http_response_code(500);
    echo 'FAIL: ' . implode("\n", $output);
}
