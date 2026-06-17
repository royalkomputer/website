<?php
session_start();

// --- KREDENSIAL DATABASE ---
define('DB_HOST', '192.168.18.189');
define('DB_PORT', '5444');
define('DB_NAME', 'i4_ROYAL');
define('DB_USER', 'admin');
define('DB_PASS', '2356988');

// --- PATH FILE ---
define('ADMINS_FILE',  __DIR__ . '/admins.json');
define('JAM_FILE',     __DIR__ . '/jam_operasional.json');
define('STATUS_FILE',  __DIR__ . '/status_toko.txt');

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
    
    // Kembalikan konfigurasi jam atau default
    $result = $data ?: $default;

    return $result;
}

// ============================================================
// SCHEDULE TUTUP SEMENTARA
// ============================================================

define('SCHEDULE_FILE', __DIR__ . '/jadwal_tutup.json');

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

function requireLogin(): void {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit;
    }
}