<?php
session_start();

// --- KREDENSIAL DATABASE ---
// Environment variables for cloud (Neon) with local IPOS fallback
define('DB_HOST', getenv('PGHOST') ?: '192.168.18.189');
define('DB_PORT', getenv('PGPORT') ?: '5444');
define('DB_NAME', getenv('PGDATABASE') ?: 'i4_ROYAL');
define('DB_USER', getenv('PGUSER') ?: 'admin');
define('DB_PASS', getenv('PGPASSWORD') ?: '2356988');

// --- PATH FILE (data/ subdirectory) ---
define('ADMINS_FILE',  __DIR__ . '/data/admins.json');
define('JAM_FILE',     __DIR__ . '/data/jam_operasional.json');
define('SCHEDULE_FILE', __DIR__ . '/data/jadwal_tutup.json');
define('STATUS_FILE',  __DIR__ . '/data/status_toko.txt');
define('TAGLINE_FILE', __DIR__ . '/data/tagline.json');

// --- FUNGSI KONEKSI DATABASE ---
function getDBConnection() {
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . " connect_timeout=3";
    return @pg_connect($conn_string);
}

// ============================================================
// HELPER: MANAJEMEN ADMIN
// ============================================================

function loadAdmins(): array {
    if (!file_exists(ADMINS_FILE)) {
        // Buat file admins.json dengan super admin default jika belum ada
        $default = [
            'admins' => [[
                'id'            => '1',
                'username'      => 'superadmin',
                'password_hash' => password_hash('royal2026', PASSWORD_BCRYPT),
                'role'          => 'super_admin',
                'nama'          => 'Super Admin',
                'created_at'    => date('Y-m-d')
            ]]
        ];
        file_put_contents(ADMINS_FILE, json_encode($default, JSON_PRETTY_PRINT));
        return $default['admins'];
    }
    $data = json_decode(file_get_contents(ADMINS_FILE), true);
    return $data['admins'] ?? [];
}

function saveAdmins(array $admins): bool {
    return file_put_contents(ADMINS_FILE, json_encode(['admins' => $admins], JSON_PRETTY_PRINT)) !== false;
}

function findAdminByUsername(string $username): ?array {
    foreach (loadAdmins() as $admin) {
        if ($admin['username'] === $username) return $admin;
    }
    return null;
}

function findAdminById(string $id): ?array {
    foreach (loadAdmins() as $admin) {
        if ($admin['id'] === $id) return $admin;
    }
    return null;
}

function generateAdminId(): string {
    $admins = loadAdmins();
    $ids = array_column($admins, 'id');
    return (string)(empty($ids) ? 1 : (max(array_map('intval', $ids)) + 1));
}

// ============================================================
// HELPER: JAM OPERASIONAL
// ============================================================

function loadJamOperasional(): array {
    $default = [
        'Monday'    => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Senin',    'libur' => false],
        'Tuesday'   => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Selasa',   'libur' => false],
        'Wednesday' => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Rabu',     'libur' => false],
        'Thursday'  => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Kamis',    'libur' => false],
        'Friday'    => ['buka' => '13:30', 'tutup' => '22:00', 'indo' => 'Jumat',    'libur' => false],
        'Saturday'  => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Sabtu',    'libur' => false],
        'Sunday'    => ['buka' => '09:00', 'tutup' => '21:00', 'indo' => 'Minggu',   'libur' => false],
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
    $result = file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
    // Sync ke frontend untuk immediate update
    if ($result !== false) {
        @file_put_contents(__DIR__ . '/../frontend/jadwal_tutup.json', json_encode($schedules, JSON_PRETTY_PRINT));
    }
    return $result !== false;
}

// ============================================================
// TAGLINE TOKO
// ============================================================

define('TAGLINE_DEFAULT', 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.');

function loadTagline(): string {
    if (!file_exists(TAGLINE_FILE)) {
        @file_put_contents(TAGLINE_FILE, json_encode(['tagline' => TAGLINE_DEFAULT], JSON_PRETTY_PRINT));
        return TAGLINE_DEFAULT;
    }
    $data = json_decode(file_get_contents(TAGLINE_FILE), true);
    return $data['tagline'] ?? TAGLINE_DEFAULT;
}

function saveTagline(string $tagline): bool {
    $result = file_put_contents(TAGLINE_FILE, json_encode(['tagline' => $tagline], JSON_PRETTY_PRINT));
    // Sync ke frontend untuk immediate update
    if ($result !== false) {
        @file_put_contents(__DIR__ . '/../frontend/tagline.json', json_encode(['tagline' => $tagline], JSON_PRETTY_PRINT));
    }
    return $result !== false;
}

// ============================================================
// HELPER: SESSION
// ============================================================

function getCurrentAdmin(): ?array {
    if (!isset($_SESSION['admin_id'])) return null;
    return findAdminById($_SESSION['admin_id']);
}

function isSuperAdmin(): bool {
    $admin = getCurrentAdmin();
    return $admin && $admin['role'] === 'super_admin';
}

/** @codeCoverageIgnore */
function requireLogin(): void {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit;
    }
}
