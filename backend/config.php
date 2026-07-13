<?php
// Persist sessions across container restarts (Docker named volume)
if (is_dir('/var/lib/php/sessions')) {
    session_save_path('/var/lib/php/sessions');
}
session_start();

// Load .env file (local development)
$env_vars = [];
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        putenv($line);
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env_vars[$parts[0]] = $parts[1];
        }
    }
}
// --- KREDENSIAL DATABASE ---
// Di VPS/Docker: DB_HOST mengarah ke service 'db' (docker-compose)
// Di lokal: set env var sesuai environment masing-masing
define('DB_HOST', getenv('DB_HOST') ?: getenv('PGHOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: getenv('PGDATABASE') ?: 'royalkomputer');
define('DB_USER', getenv('DB_USER') ?: getenv('PGUSER') ?: 'royal_owner');
define('DB_PASS', getenv('DB_PASSWORD') ?: getenv('PGPASSWORD') ?: 'royalkomputer2026');

// --- PATH FILE (data/ subdirectory) ---
define('ADMINS_FILE',  __DIR__ . '/data/admins.json');
define('SCHEDULE_FILE', __DIR__ . '/data/jadwal_tutup.json');
define('STATUS_FILE',  __DIR__ . '/data/status_toko.txt');
define('TAGLINE_FILE',    __DIR__ . '/data/tagline.json');
define('PRODUCT_INFO_FILE', __DIR__ . '/data/product_info.json');
define('HEADING_FILE',      __DIR__ . '/data/heading.json');
define('BANNER_FILE',       __DIR__ . '/data/banners.json');


define('TAGLINE_DEFAULT', 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.');
define('PRODUCT_INFO_DEFAULT', 'Perhatian! Harga tidak selalu update. Silahkan hubungi Kami di WhatsApp.');
define('HEADING_DEFAULT_PREFIX', 'Solusi Hardware di');
define('HEADING_DEFAULT_BRAND', 'Royal Komputer');

// ============================================================
// DB CONNECTION (auto-creates config tables on first use)
// ============================================================

function getDB() {
    static $conn = null;
    if ($conn !== null) return $conn;
    if (!function_exists('pg_connect')) return null;
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . " connect_timeout=3";
    $conn = @pg_connect($conn_string);
    if ($conn) {
        migrateConfigTables($conn);
    }
    return $conn ?: null;
}

function getDBConnection() {
    $c = getDB();
    return $c ? $c : false;
}

function migrateConfigTables($conn): void {
    $sql = "
    CREATE TABLE IF NOT EXISTS admins (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'admin',
        nama VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    INSERT INTO admins (id, username, password_hash, role, nama)
    SELECT 1, 'superadmin', '" . pg_escape_string($conn, password_hash('royal2026', PASSWORD_BCRYPT)) . "', 'super_admin', 'Super Admin'
    WHERE NOT EXISTS (SELECT 1 FROM admins);

    CREATE TABLE IF NOT EXISTS jadwal_tutup (
        id VARCHAR(50) PRIMARY KEY,
        start_time TIMESTAMP NOT NULL,
        end_time TIMESTAMP NOT NULL,
        note TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS status_toko (
        id INTEGER PRIMARY KEY DEFAULT 1,
        status VARCHAR(10) NOT NULL DEFAULT 'buka'
    );
    INSERT INTO status_toko (id, status) VALUES (1, 'buka') ON CONFLICT (id) DO NOTHING;

    CREATE TABLE IF NOT EXISTS tagline (
        id INTEGER PRIMARY KEY DEFAULT 1,
        text TEXT NOT NULL DEFAULT '" . pg_escape_string($conn, TAGLINE_DEFAULT) . "'
    );
    INSERT INTO tagline (id, text) VALUES (1, '" . pg_escape_string($conn, TAGLINE_DEFAULT) . "') ON CONFLICT (id) DO NOTHING;

    CREATE TABLE IF NOT EXISTS product_info (
        id INTEGER PRIMARY KEY DEFAULT 1,
        text TEXT NOT NULL DEFAULT '" . pg_escape_string($conn, PRODUCT_INFO_DEFAULT) . "'
    );
    INSERT INTO product_info (id, text) VALUES (1, '" . pg_escape_string($conn, PRODUCT_INFO_DEFAULT) . "') ON CONFLICT (id) DO NOTHING;

    CREATE TABLE IF NOT EXISTS heading (
        id INTEGER PRIMARY KEY DEFAULT 1,
        prefix VARCHAR(255) NOT NULL DEFAULT 'Solusi Hardware di',
        brand VARCHAR(255) NOT NULL DEFAULT 'Royal Komputer'
    );
    INSERT INTO heading (id, prefix, brand) VALUES (1, 'Solusi Hardware di', 'Royal Komputer') ON CONFLICT (id) DO NOTHING;

    CREATE TABLE IF NOT EXISTS admin_history (
        id SERIAL PRIMARY KEY,
        admin_id INTEGER NOT NULL,
        admin_username VARCHAR(100) NOT NULL,
        admin_nama VARCHAR(255) NOT NULL DEFAULT '',
        action VARCHAR(50) NOT NULL,
        target_type VARCHAR(50) DEFAULT '',
        target_id VARCHAR(100) DEFAULT '',
        detail TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    @pg_query($conn, $sql);
}

// ============================================================
// HELPER: MANAJEMEN ADMIN (DB primary, file fallback)
// ============================================================

function loadAdmins(): array {
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT id::text, username, password_hash, role, nama, to_char(created_at, 'YYYY-MM-DD') AS created_at FROM admins ORDER BY id");
        if ($r && pg_num_rows($r) > 0) {
            $admins = [];
            while ($row = pg_fetch_assoc($r)) {
                $row['id'] = (string)$row['id'];
                $admins[] = $row;
            }
            return $admins;
        }
    }
    if (!file_exists(ADMINS_FILE)) {
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
    $db = getDB();
    if ($db) {
        @pg_query($db, "BEGIN");
        @pg_query($db, "DELETE FROM admins");
        foreach ($admins as $a) {
            $id = (int)($a['id'] ?? 0);
            $u = $a['username'];
            $p = $a['password_hash'];
            $r = $a['role'] ?? 'admin';
            $n = $a['nama'] ?? $a['username'];
            $c = $a['created_at'] ?? date('Y-m-d');
            pg_query_params($db, "INSERT INTO admins (id, username, password_hash, role, nama, created_at) VALUES ($1, $2, $3, $4, $5, $6::date)", array($id, $u, $p, $r, $n, $c));
        }
        @pg_query($db, "COMMIT");
    }
    return file_put_contents(ADMINS_FILE, json_encode(['admins' => $admins], JSON_PRETTY_PRINT)) !== false;
}

function findAdminByUsername(string $username): ?array {
    $db = getDB();
    if ($db) {
        $r = @pg_query_params($db, "SELECT id::text, username, password_hash, role, nama, to_char(created_at, 'YYYY-MM-DD') AS created_at FROM admins WHERE username = $1", array($username));
        if ($r && $row = pg_fetch_assoc($r)) {
            $row['id'] = (string)$row['id'];
            return $row;
        }
        return null;
    }
    foreach (loadAdmins() as $admin) {
        if ($admin['username'] === $username) return $admin;
    }
    return null;
}

function findAdminById(string $id): ?array {
    $db = getDB();
    if ($db) {
        $id_int = (int)$id;
        $r = @pg_query_params($db, "SELECT id::text, username, password_hash, role, nama, to_char(created_at, 'YYYY-MM-DD') AS created_at FROM admins WHERE id = $1", array($id_int));
        if ($r && $row = pg_fetch_assoc($r)) {
            $row['id'] = (string)$row['id'];
            return $row;
        }
        return null;
    }
    foreach (loadAdmins() as $admin) {
        if ($admin['id'] === $id) return $admin;
    }
    return null;
}

function generateAdminId(): string {
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM admins");
        if ($r && $row = pg_fetch_assoc($r)) {
            return (string)$row['next_id'];
        }
    }
    $admins = loadAdmins();
    $ids = array_column($admins, 'id');
    return (string)(empty($ids) ? 1 : (max(array_map('intval', $ids)) + 1));
}

// ============================================================
// SCHEDULE TUTUP SEMENTARA (DB primary, file fallback)
// ============================================================

function loadSchedules(): array {
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT id, to_char(start_time, 'YYYY-MM-DD HH24:MI') AS start, to_char(end_time, 'YYYY-MM-DD HH24:MI') AS end, note, to_char(created_at, 'YYYY-MM-DD HH24:MI') AS created_at FROM jadwal_tutup ORDER BY start_time");
        if ($r && pg_num_rows($r) > 0) {
            return pg_fetch_all($r) ?: [];
        }
    }
    if (!file_exists(SCHEDULE_FILE)) {
        file_put_contents(SCHEDULE_FILE, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $data = json_decode(file_get_contents(SCHEDULE_FILE), true);
    return $data ?: [];
}

function saveSchedules(array $schedules): bool {
    $db = getDB();
    if ($db) {
        @pg_query($db, "DELETE FROM jadwal_tutup");
        foreach ($schedules as $s) {
            $id = $s['id'] ?? uniqid('s_');
            $start = $s['start'];
            $end = $s['end'];
            $note = $s['note'] ?? '';
            $created = $s['created_at'] ?? date('Y-m-d H:i');
            pg_query_params($db, "INSERT INTO jadwal_tutup (id, start_time, end_time, note, created_at) VALUES ($1, $2, $3, $4, $5::timestamp)", array($id, $start, $end, $note, $created));
        }
    }
    $result = file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
    return $result !== false;
}

// ============================================================
// STATUS TOKO (DB primary, file fallback)
// ============================================================

function loadStatus(): string {
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT status FROM status_toko WHERE id = 1");
        if ($r && $row = pg_fetch_assoc($r)) {
            return $row['status'];
        }
    }
    if (file_exists(STATUS_FILE)) {
        return trim(file_get_contents(STATUS_FILE));
    }
    return 'buka';
}

function saveStatus(string $status): bool {
    $db = getDB();
    if ($db) {
        $s = $status === 'tutup' ? 'tutup' : 'buka';
        pg_query_params($db, "INSERT INTO status_toko (id, status) VALUES (1, $1) ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status", array($s));
    }
    $result = file_put_contents(STATUS_FILE, $status === 'tutup' ? 'tutup' : 'buka');
    return $result !== false;
}

// ============================================================
// TAGLINE TOKO (DB primary, file fallback)
// ============================================================

function loadTagline(): string {
    // File is primary source of truth (synced via git to production)
    if (file_exists(TAGLINE_FILE)) {
        $data = json_decode(file_get_contents(TAGLINE_FILE), true);
        if (!empty($data['tagline'])) {
            return $data['tagline'];
        }
    }
    // Fallback: database
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT text FROM tagline WHERE id = 1");
        if ($r && $row = pg_fetch_assoc($r)) {
            return $row['text'];
        }
    }
    return TAGLINE_DEFAULT;
}

function saveTagline(string $tagline): bool {
    $db = getDB();
    if ($db) {
        pg_query_params($db, "INSERT INTO tagline (id, text) VALUES (1, $1) ON CONFLICT (id) DO UPDATE SET text = EXCLUDED.text", array($tagline));
    }
    $result = file_put_contents(TAGLINE_FILE, json_encode(['tagline' => $tagline], JSON_PRETTY_PRINT));
    return $result !== false;
}

// ============================================================
// PRODUCT INFO TEKS (DB primary, file fallback)
// ============================================================

function loadProductInfoText(): string {
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT text FROM product_info WHERE id = 1");
        if ($r && $row = pg_fetch_assoc($r)) {
            return $row['text'];
        }
    }
    if (!file_exists(PRODUCT_INFO_FILE)) {
        @file_put_contents(PRODUCT_INFO_FILE, json_encode(['text' => PRODUCT_INFO_DEFAULT], JSON_PRETTY_PRINT));
        return PRODUCT_INFO_DEFAULT;
    }
    $data = json_decode(file_get_contents(PRODUCT_INFO_FILE), true);
    return $data['text'] ?? PRODUCT_INFO_DEFAULT;
}

function saveProductInfoText(string $text): bool {
    $db = getDB();
    if ($db) {
        pg_query_params($db, "INSERT INTO product_info (id, text) VALUES (1, $1) ON CONFLICT (id) DO UPDATE SET text = EXCLUDED.text", array($text));
    }
    $result = file_put_contents(PRODUCT_INFO_FILE, json_encode(['text' => $text], JSON_PRETTY_PRINT));
    return $result !== false;
}

// ============================================================
// HEADING TOKO (DB primary, file fallback)
// ============================================================

function loadHeading(): array {
    $default = ['prefix' => HEADING_DEFAULT_PREFIX, 'brand' => HEADING_DEFAULT_BRAND];
    // Prioritaskan file
    if (file_exists(HEADING_FILE)) {
        $data = json_decode(file_get_contents(HEADING_FILE), true);
        if (!empty($data['prefix'])) {
            return [
                'prefix' => $data['prefix'],
                'brand'  => $data['brand'] ?? HEADING_DEFAULT_BRAND,
            ];
        }
    }
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT prefix, brand FROM heading WHERE id = 1");
        if ($r && $row = pg_fetch_assoc($r)) {
            return ['prefix' => $row['prefix'], 'brand' => $row['brand']];
        }
    }
    return $default;
}

function saveHeading(string $prefix, string $brand): bool {
    $data = ['prefix' => $prefix, 'brand' => $brand];
    $db = getDB();
    if ($db) {
        pg_query_params($db, "INSERT INTO heading (id, prefix, brand) VALUES (1, $1, $2) ON CONFLICT (id) DO UPDATE SET prefix = EXCLUDED.prefix, brand = EXCLUDED.brand", array($prefix, $brand));
    }
    $result = file_put_contents(HEADING_FILE, json_encode($data, JSON_PRETTY_PRINT));
    return $result !== false;
}

// ============================================================
// BANNER PLAYLIST (file only, no DB)
// Format: array of playlist, each playlist has:
//   id, name, order, active, interval (ms), photos[]
// Each photo: { image, link, alt }
// ============================================================

function migrateBannersToPlaylist(array $data): array {
    // Jika data lama (flat array banner tanpa 'photos'), migrasi ke format playlist
    if (!empty($data) && !isset($data[0]['photos'])) {
        $playlist = [
            'id' => 'pl_' . uniqid(),
            'name' => 'Playlist Utama',
            'order' => 1,
            'active' => true,
            'interval' => 5000,
            'photos' => []
        ];
        foreach ($data as $b) {
            if (!empty($b['image'])) {
                $playlist['photos'][] = [
                    'image' => $b['image'],
                    'link' => $b['link'] ?? '',
                    'alt' => $b['alt'] ?? ''
                ];
            }
        }
        if (!empty($playlist['photos'])) {
            return [$playlist];
        }
    }
    return $data;
}

function loadBanners(): array {
    if (!file_exists(BANNER_FILE)) {
        @file_put_contents(BANNER_FILE, '[]');
        return [];
    }
    $data = json_decode(file_get_contents(BANNER_FILE), true);
    if (!is_array($data)) return [];
    // Auto-migrate if old format
    if (!empty($data) && !isset($data[0]['photos'])) {
        $data = migrateBannersToPlaylist($data);
        saveBanners($data);
    }
    return $data;
}

function saveBanners(array $playlists): bool {
    $result = file_put_contents(BANNER_FILE, json_encode($playlists, JSON_PRETTY_PRINT));
    return $result !== false;
}

// ============================================================
// HELPER: IMAGE MIME TYPE (safe fallback)
// ============================================================

function getImageMimeType(string $filepath): string|false {
    if (!file_exists($filepath) || !is_file($filepath)) {
        return false;
    }
    $info = @getimagesize($filepath);
    if ($info === false || !isset($info['mime'])) {
        return false;
    }
    return $info['mime'];
}

// ============================================================
// HELPER: IMAGE PROCESSING (safe, no GD dependency)
// ============================================================

function gdWebpAvailable(): bool {
    return function_exists('imagecreatefromjpeg') && function_exists('imagewebp');
}

function createImageFromFile(string $filepath): mixed {
    if (!function_exists('imagecreatefromjpeg')) {
        return false;
    }
    $mime = getImageMimeType($filepath);
    if ($mime === false) return false;
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($filepath),
        'image/png' => @imagecreatefrompng($filepath),
        'image/webp' => @imagecreatefromwebp($filepath),
        'image/gif' => @imagecreatefromgif($filepath),
        default => false,
    };
}

function convertOrCopyImage(string $sourceFile, string $destFile, int $quality = 85): bool {
    if (gdWebpAvailable()) {
        $img = createImageFromFile($sourceFile);
        if ($img) {
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($img);
            }
            if (function_exists('imagealphablending')) {
                @imagealphablending($img, true);
            }
            if (function_exists('imagesavealpha')) {
                @imagesavealpha($img, true);
            }
            $result = @imagewebp($img, $destFile, $quality);
            @imagedestroy($img);
            if ($result) return true;
        }
    }
    return copy($sourceFile, $destFile);
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
// ============================================================
// HELPER: ADMIN HISTORY LOGGING (superadmin tidak dicatat)
// ============================================================

function logAdminHistory(string $action, string $target_type = '', string $target_id = '', string $detail = ''): void {
    if (isSuperAdmin()) return;
    
    $admin = getCurrentAdmin();
    if (!$admin) return;
    
    $admin_id = (int)$admin['id'];
    $db = getDB();
    if (!$db) return;
    
    pg_query_params($db, "INSERT INTO admin_history (admin_id, admin_username, admin_nama, action, target_type, target_id, detail) VALUES ($1, $2, $3, $4, $5, $6, $7)", array($admin_id, $admin['username'], $admin['nama'] ?? $admin['username'], $action, $target_type, $target_id, $detail));
}

// ============================================================
// END OF CONFIG
// ============================================================
