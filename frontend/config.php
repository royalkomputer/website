<?php
session_start();

// --- KREDENSIAL DATABASE ---
define('DB_HOST', '192.168.18.189');
define('DB_PORT', '5444');
define('DB_NAME', 'i4_ROYAL');
define('DB_USER', 'admin');
define('DB_PASS', '2356988');

// --- PATH FILE ---
define('JAM_FILE',     __DIR__ . '/jam_operasional.json');
define('STATUS_FILE',  __DIR__ . '/status_toko.txt');
define('SCHEDULE_FILE',      __DIR__ . '/jadwal_tutup.json');
define('PRODUCT_INFO_FILE', __DIR__ . '/product_info.json');

// --- FUNGSI KONEKSI DATABASE ---
function getDBConnection() {
    if (!function_exists('pg_connect')) {
        return false;
    }
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . " connect_timeout=3";
    return @pg_connect($conn_string);
}

// ============================================================
// HELPER: JAM OPERASIONAL
// ============================================================

function loadJamOperasional(): array {
    $default = [
        'Monday'    => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Senin'],
        'Tuesday'   => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Selasa'],
        'Wednesday' => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Rabu'],
        'Thursday'  => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Kamis'],
        'Friday'    => ['buka' => '13:30', 'tutup' => '22:00', 'indo' => 'Jumat'],
        'Saturday'  => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Sabtu'],
        'Sunday'    => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Minggu'],
    ];
    if (!file_exists(JAM_FILE)) {
        file_put_contents(JAM_FILE, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $data = json_decode(file_get_contents(JAM_FILE), true);
    return $data ?: $default;
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
    $default = 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.';
    if (!file_exists(PRODUCT_INFO_FILE)) {
        return $default;
    }
    $data = json_decode(file_get_contents(PRODUCT_INFO_FILE), true);
    return $data['text'] ?? $default;
}
