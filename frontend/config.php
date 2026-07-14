<?php
session_start();

// --- KREDENSIAL DATABASE ---
define('DB_HOST', '192.168.18.189');
define('DB_PORT', '5444');
define('DB_NAME', 'i4_ROYAL');
define('DB_USER', 'admin');
define('DB_PASS', '2356988');

// --- PATH FILE ---
define('STATUS_FILE',  __DIR__ . '/status_toko.txt');
define('SCHEDULE_FILE',      __DIR__ . '/jadwal_tutup.json');
define('PRODUCT_INFO_FILE', __DIR__ . '/product_info.json');

// --- BACKEND URL UNTUK ASSETS ---
// Di VPS: frontend & backend satu domain via Caddy, tidak perlu URL terpisah
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

// ============================================================
// JAM OPERASIONAL TOKO
// ============================================================

function loadJamOperasional(): array {
    $default = [
        'Monday'    => ['indo' => 'Senin',    'buka' => '08:00', 'tutup' => '22:00'],
        'Tuesday'   => ['indo' => 'Selasa',   'buka' => '08:00', 'tutup' => '22:00'],
        'Wednesday' => ['indo' => 'Rabu',     'buka' => '08:00', 'tutup' => '22:00'],
        'Thursday'  => ['indo' => 'Kamis',    'buka' => '08:00', 'tutup' => '22:00'],
        'Friday'    => ['indo' => 'Jumat',    'buka' => '08:00', 'tutup' => '22:00'],
        'Saturday'  => ['indo' => 'Sabtu',    'buka' => '08:00', 'tutup' => '22:00'],
        'Sunday'    => ['indo' => 'Minggu',   'buka' => '00:00', 'tutup' => '00:00', 'libur' => true],
    ];

    $file = __DIR__ . '/jam_buka.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data) && !empty($data)) {
            return $data;
        }
    }

    return $default;
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
