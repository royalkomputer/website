<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Asia/Jakarta');

// Don't start session here — backend/config.php does it

// --- Back up real data dir and replace with test fixtures ---
// This ensures backend/config.php's define() executes but reads from fixtures
$realDataDir = __DIR__ . '/../backend/data';
$bakDir = sys_get_temp_dir() . '/royal_bak_' . bin2hex(random_bytes(4));
$restoreNeeded = false;

if (is_dir($realDataDir)) {
    mkdir($bakDir, 0777, true);
    $files = glob($realDataDir . '/*');
    foreach ($files as $f) {
        copy($f, $bakDir . '/' . basename($f));
    }
    // Clear data dir
    foreach ($files as $f) {
        is_file($f) && unlink($f);
    }
    $restoreNeeded = true;
} else {
    mkdir($realDataDir, 0777, true);
}

// Copy fixture files into the real data dir
$fixtures = [
    'admins.json'       => __DIR__ . '/fixtures/admins.json',
    'jam_operasional.json' => __DIR__ . '/fixtures/jam_operasional.json',
    'jadwal_tutup.json' => __DIR__ . '/fixtures/jadwal_tutup.json',
    'status_toko.txt'   => __DIR__ . '/fixtures/status_toko.txt',
];
foreach ($fixtures as $name => $src) {
    if (file_exists($src)) {
        copy($src, $realDataDir . '/' . $name);
    }
}

// Register restore handler to put original data back
register_shutdown_function(function () use ($bakDir, $realDataDir, $restoreNeeded) {
    if ($restoreNeeded && is_dir($bakDir)) {
        $files = glob($bakDir . '/*');
        foreach ($files as $f) {
            copy($f, $realDataDir . '/' . basename($f));
            unlink($f);
        }
        rmdir($bakDir);
    }
});

// Load source files
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/cors.php';

// --- Helper: copy fixture to test data dir ---
function load_fixture(string $name): string {
    $src = __DIR__ . '/fixtures/' . $name;
    $dst = __DIR__ . '/../backend/data/' . $name;
    copy($src, $dst);
    return $dst;
}

function cleanup_test_data(): void {
    // No-op — data dir reset happens via shutdown function
}
