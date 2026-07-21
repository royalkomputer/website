<?php
// Load .env (coba local dulu, fallback ke ../backend/.env)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        putenv($line);
    }
} elseif (file_exists(__DIR__ . '/../backend/.env')) {
    $lines = file(__DIR__ . '/../backend/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        putenv($line);
    }
}

session_start();

// --- KREDENSIAL DATABASE ---
// Default: VPS PostgreSQL local. Override via .env atau env var.
define('DB_HOST', getenv('DB_HOST') ?: getenv('PGHOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: getenv('PGDATABASE') ?: 'royalkomputer');
define('DB_USER', getenv('DB_USER') ?: getenv('PGUSER') ?: 'royal_owner');
define('DB_PASS', getenv('DB_PASSWORD') ?: getenv('PGPASSWORD') ?: '');

// --- PATH FILE ---
define('STATUS_FILE',  __DIR__ . '/status_toko.txt');
define('SCHEDULE_FILE',      __DIR__ . '/jadwal_tutup.json');
define('PRODUCT_INFO_FILE', __DIR__ . '/product_info.json');

// --- BACKEND URL UNTUK ASSETS ---
// Di VPS manual: frontend & backend satu server, tidak perlu URL terpisah
define('BACKEND_URL', getenv('BACKEND_URL') ?: '');

// --- FUNGSI KONEKSI DATABASE ---
function getDBConnection() {
    if (!function_exists('pg_connect')) {
        return false;
    }
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . " connect_timeout=3";
    return @pg_connect($conn_string);
}

// ============================================================
// SCHEDULE TUTUP SEMENTARA
// ============================================================

function loadSchedules(): array {
    if (!file_exists(SCHEDULE_FILE)) {
        file_put_contents(SCHEDULE_FILE, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $data = json_decode(file_get_contents(SCHEDULE_FILE), true);
    return $data ?: [];
}

function saveSchedules(array $schedules): bool {
    return file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT)) !== false;
}

// ============================================================
// PRODUCT INFO TEKS
// ============================================================

function loadProductInfoText(): string {
    $default = 'Perhatian! Harga tidak selalu update. Silahkan hubungi Kami di WhatsApp.';
    if (!file_exists(PRODUCT_INFO_FILE)) {
        return $default;
    }
    $data = json_decode(file_get_contents(PRODUCT_INFO_FILE), true);
    return $data['text'] ?? $default;
}
