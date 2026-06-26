<?php
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
define('ENV_GIT_TOKEN', $env_vars['GIT_TOKEN'] ?? getenv('GIT_TOKEN') ?: '');
define('ENV_GIT_REPO_URL', $env_vars['GIT_REPO_URL'] ?? getenv('GIT_REPO_URL') ?: '');

// --- KREDENSIAL DATABASE ---
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
define('TAGLINE_FILE',    __DIR__ . '/data/tagline.json');
define('PRODUCT_INFO_FILE', __DIR__ . '/data/product_info.json');
define('HEADING_FILE',      __DIR__ . '/data/heading.json');

// --- FRONTEND SYNC PATH ---
define('FE_DIR', __DIR__ . '/../frontend');

define('TAGLINE_DEFAULT', 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.');
define('PRODUCT_INFO_DEFAULT', 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.');
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

    CREATE TABLE IF NOT EXISTS jam_operasional (
        day VARCHAR(20) PRIMARY KEY,
        buka VARCHAR(5) DEFAULT '09:00',
        tutup VARCHAR(5) DEFAULT '21:00',
        indo VARCHAR(20) NOT NULL,
        libur BOOLEAN DEFAULT FALSE
    );
    INSERT INTO jam_operasional (day, buka, tutup, indo, libur) VALUES
        ('Monday', '09:00', '21:00', 'Senin', FALSE),
        ('Tuesday', '09:00', '21:00', 'Selasa', FALSE),
        ('Wednesday', '09:00', '21:00', 'Rabu', FALSE),
        ('Thursday', '09:00', '21:00', 'Kamis', FALSE),
        ('Friday', '13:30', '22:00', 'Jumat', FALSE),
        ('Saturday', '09:00', '21:00', 'Sabtu', FALSE),
        ('Sunday', '09:00', '21:00', 'Minggu', FALSE)
    ON CONFLICT (day) DO NOTHING;

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
        @pg_query($db, "DELETE FROM admins");
        foreach ($admins as $a) {
            $id = (int)($a['id'] ?? 0);
            $u = pg_escape_string($db, $a['username']);
            $p = pg_escape_string($db, $a['password_hash']);
            $r = pg_escape_string($db, $a['role'] ?? 'admin');
            $n = pg_escape_string($db, $a['nama'] ?? $a['username']);
            $c = pg_escape_string($db, $a['created_at'] ?? date('Y-m-d'));
            @pg_query($db, "INSERT INTO admins (id, username, password_hash, role, nama, created_at) VALUES ($id, '$u', '$p', '$r', '$n', '$c'::date)");
        }
    }
    return file_put_contents(ADMINS_FILE, json_encode(['admins' => $admins], JSON_PRETTY_PRINT)) !== false;
}

function findAdminByUsername(string $username): ?array {
    $db = getDB();
    if ($db) {
        $u = pg_escape_string($db, $username);
        $r = @pg_query($db, "SELECT id::text, username, password_hash, role, nama, to_char(created_at, 'YYYY-MM-DD') AS created_at FROM admins WHERE username = '$u'");
        if ($r && $row = pg_fetch_assoc($r)) {
            $row['id'] = (string)$row['id'];
            return $row;
        }
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
        $r = @pg_query($db, "SELECT id::text, username, password_hash, role, nama, to_char(created_at, 'YYYY-MM-DD') AS created_at FROM admins WHERE id = $id_int");
        if ($r && $row = pg_fetch_assoc($r)) {
            $row['id'] = (string)$row['id'];
            return $row;
        }
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
// HELPER: JAM OPERASIONAL (DB primary, file fallback)
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

    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT day, buka, tutup, indo, libur FROM jam_operasional ORDER BY
            CASE day WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 END");
        if ($r && pg_num_rows($r) > 0) {
            $result = [];
            while ($row = pg_fetch_assoc($r)) {
                $result[$row['day']] = [
                    'buka'  => $row['buka'] ?? '',
                    'tutup' => $row['tutup'] ?? '',
                    'indo'  => $row['indo'],
                    'libur' => $row['libur'] === 't',
                ];
            }
            return $result;
        }
    }

    if (!file_exists(JAM_FILE)) {
        file_put_contents(JAM_FILE, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $data = json_decode(file_get_contents(JAM_FILE), true);
    return $data ?: $default;
}

function saveJamOperasional(array $jam): bool {
    $db = getDB();
    if ($db) {
        @pg_query($db, "DELETE FROM jam_operasional");
        foreach ($jam as $day => $d) {
            $day_e = pg_escape_string($db, $day);
            $buka = pg_escape_string($db, $d['buka'] ?? '');
            $tutup = pg_escape_string($db, $d['tutup'] ?? '');
            $indo = pg_escape_string($db, $d['indo'] ?? $day);
            $libur = !empty($d['libur']) ? 'true' : 'false';
            @pg_query($db, "INSERT INTO jam_operasional (day, buka, tutup, indo, libur) VALUES ('$day_e', '$buka', '$tutup', '$indo', $libur)");
        }
    }
    $result = file_put_contents(JAM_FILE, json_encode($jam, JSON_PRETTY_PRINT));
    if ($result !== false) {
        @file_put_contents(FE_DIR . '/jam_operasional.json', json_encode($jam, JSON_PRETTY_PRINT));
    }
    return $result !== false;
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
            $id = pg_escape_string($db, $s['id'] ?? uniqid('s_'));
            $start = pg_escape_string($db, $s['start']);
            $end = pg_escape_string($db, $s['end']);
            $note = pg_escape_string($db, $s['note'] ?? '');
            $created = pg_escape_string($db, $s['created_at'] ?? date('Y-m-d H:i'));
            @pg_query($db, "INSERT INTO jadwal_tutup (id, start_time, end_time, note, created_at) VALUES ('$id', '$start', '$end', '$note', '$created'::timestamp)");
        }
    }
    $result = file_put_contents(SCHEDULE_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
    if ($result !== false) {
        @file_put_contents(FE_DIR . '/jadwal_tutup.json', json_encode($schedules, JSON_PRETTY_PRINT));
    }
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
        $s = pg_escape_string($db, $status === 'tutup' ? 'tutup' : 'buka');
        @pg_query($db, "INSERT INTO status_toko (id, status) VALUES (1, '$s') ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status");
    }
    $result = file_put_contents(STATUS_FILE, $status === 'tutup' ? 'tutup' : 'buka');
    if ($result !== false) {
        @file_put_contents(FE_DIR . '/status_toko.txt', $status === 'tutup' ? 'tutup' : 'buka');
    }
    return $result !== false;
}

// ============================================================
// TAGLINE TOKO (DB primary, file fallback)
// ============================================================

function loadTagline(): string {
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT text FROM tagline WHERE id = 1");
        if ($r && $row = pg_fetch_assoc($r)) {
            return $row['text'];
        }
    }
    if (!file_exists(TAGLINE_FILE)) {
        @file_put_contents(TAGLINE_FILE, json_encode(['tagline' => TAGLINE_DEFAULT], JSON_PRETTY_PRINT));
        return TAGLINE_DEFAULT;
    }
    $data = json_decode(file_get_contents(TAGLINE_FILE), true);
    return $data['tagline'] ?? TAGLINE_DEFAULT;
}

function saveTagline(string $tagline): bool {
    $db = getDB();
    if ($db) {
        $t = pg_escape_string($db, $tagline);
        @pg_query($db, "INSERT INTO tagline (id, text) VALUES (1, '$t') ON CONFLICT (id) DO UPDATE SET text = EXCLUDED.text");
    }
    $result = file_put_contents(TAGLINE_FILE, json_encode(['tagline' => $tagline], JSON_PRETTY_PRINT));
    if ($result !== false) {
        @file_put_contents(FE_DIR . '/tagline.json', json_encode(['tagline' => $tagline], JSON_PRETTY_PRINT));
    }
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
        $t = pg_escape_string($db, $text);
        @pg_query($db, "INSERT INTO product_info (id, text) VALUES (1, '$t') ON CONFLICT (id) DO UPDATE SET text = EXCLUDED.text");
    }
    $result = file_put_contents(PRODUCT_INFO_FILE, json_encode(['text' => $text], JSON_PRETTY_PRINT));
    if ($result !== false) {
        @file_put_contents(FE_DIR . '/product_info.json', json_encode(['text' => $text], JSON_PRETTY_PRINT));
    }
    return $result !== false;
}

// ============================================================
// HEADING TOKO (DB primary, file fallback)
// ============================================================

function loadHeading(): array {
    $default = ['prefix' => HEADING_DEFAULT_PREFIX, 'brand' => HEADING_DEFAULT_BRAND];
    $db = getDB();
    if ($db) {
        $r = @pg_query($db, "SELECT prefix, brand FROM heading WHERE id = 1");
        if ($r && $row = pg_fetch_assoc($r)) {
            return ['prefix' => $row['prefix'], 'brand' => $row['brand']];
        }
    }
    if (!file_exists(HEADING_FILE)) {
        @file_put_contents(HEADING_FILE, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    $data = json_decode(file_get_contents(HEADING_FILE), true);
    return [
        'prefix' => $data['prefix'] ?? HEADING_DEFAULT_PREFIX,
        'brand'  => $data['brand']  ?? HEADING_DEFAULT_BRAND,
    ];
}

function saveHeading(string $prefix, string $brand): bool {
    $data = ['prefix' => $prefix, 'brand' => $brand];
    $db = getDB();
    if ($db) {
        $p = pg_escape_string($db, $prefix);
        $b = pg_escape_string($db, $brand);
        @pg_query($db, "INSERT INTO heading (id, prefix, brand) VALUES (1, '$p', '$b') ON CONFLICT (id) DO UPDATE SET prefix = EXCLUDED.prefix, brand = EXCLUDED.brand");
    }
    $result = file_put_contents(HEADING_FILE, json_encode($data, JSON_PRETTY_PRINT));
    if ($result !== false) {
        @file_put_contents(FE_DIR . '/heading.json', json_encode($data, JSON_PRETTY_PRINT));
    }
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
    
    $username = pg_escape_string($db, $admin['username']);
    $nama = pg_escape_string($db, $admin['nama'] ?? $admin['username']);
    $act = pg_escape_string($db, $action);
    $tt = pg_escape_string($db, $target_type);
    $ti = pg_escape_string($db, $target_id);
    $det = pg_escape_string($db, $detail);
    
    @pg_query($db, "INSERT INTO admin_history (admin_id, admin_username, admin_nama, action, target_type, target_id, detail) VALUES ($admin_id, '$username', '$nama', '$act', '$tt', '$ti', '$det')");
}

// ============================================================
// GIT BACKUP: Backend -> Git (untuk Render ephemeral storage)
// ============================================================

function backupToGit(): array {
    $git_token = ENV_GIT_TOKEN ?: getenv('GIT_TOKEN');
    $repo_url  = ENV_GIT_REPO_URL ?: getenv('GIT_REPO_URL');
    $branch    = getenv('GIT_BRANCH') ?: 'main';

    if (!$git_token || !$repo_url) {
        return ['success' => false, 'message' => 'GIT_TOKEN/GIT_REPO_URL not set — skip backup'];
    }

    exec('git --version 2>&1', $ver_out, $ver_code);
    if ($ver_code !== 0) {
        return ['success' => false, 'message' => 'Git tidak tersedia di container ini'];
    }

    $cwd = getcwd();
    chdir(__DIR__);

    exec('git config user.email "royal-backup@royalkomputer.com" 2>&1');
    exec('git config user.name "Royal Auto Backup" 2>&1');

    exec('git add -A uploads/ data/ 2>&1', $add_out, $add_code);
    if ($add_code !== 0) {
        chdir($cwd);
        return ['success' => false, 'message' => 'git add gagal: ' . implode(', ', $add_out)];
    }

    exec('git diff --cached --quiet 2>&1', $diff_out, $diff_code);
    if ($diff_code === 0) {
        chdir($cwd);
        return ['success' => true, 'message' => 'Tidak ada perubahan untuk di-backup'];
    }

    $msg = 'backup: admin changes ' . date('Y-m-d H:i:s');
    $escaped_msg = str_replace('"', '\\"', $msg);
    exec("git commit -m \"$escaped_msg\" 2>&1", $commit_out, $commit_code);
    if ($commit_code !== 0) {
        chdir($cwd);
        return ['success' => true, 'message' => 'Tidak ada perubahan baru untuk di-commit'];
    }

    $auth_url = str_replace('https://', "https://x-access-token:$git_token@", $repo_url);
    exec("git remote set-url origin \"$auth_url\" 2>&1", $remote_out, $remote_code);

    $escaped_branch = escapeshellarg($branch);
    exec("git push $escaped_branch 2>&1", $push_out, $push_code);
    $push_output = implode(', ', $push_out);

    exec("git remote set-url origin \"$repo_url\" 2>&1");

    chdir($cwd);

    if ($push_code === 0) {
        return ['success' => true, 'message' => 'Perubahan berhasil di-push ke git'];
    } else {
        return ['success' => false, 'message' => 'git push gagal: ' . $push_output];
    }
}

function backupPhotosToGit(): array {
    $git_token = ENV_GIT_TOKEN ?: getenv('GIT_TOKEN');
    $repo_url  = ENV_GIT_REPO_URL ?: getenv('GIT_REPO_URL');
    $branch    = getenv('GIT_BRANCH') ?: 'main';

    // Hanya jalan di production (Render) ketika env var sudah di-set
    if (!$git_token || !$repo_url) {
        return ['success' => false, 'message' => 'GIT_TOKEN/GIT_REPO_URL not set — skip backup'];
    }

    // Cek apakah git tersedia
    exec('git --version 2>&1', $ver_out, $ver_code);
    if ($ver_code !== 0) {
        return ['success' => false, 'message' => 'Git tidak tersedia di container ini'];
    }

    $cwd = getcwd();
    chdir(__DIR__);

    // Konfigurasi git user
    exec('git config user.email "royal-backup@royalkomputer.com" 2>&1');
    exec('git config user.name "Royal Auto Backup" 2>&1');

    // Stage file uploads (git add akan mendeteksi sendiri apakah ada perubahan)
    exec('git add -A uploads/ 2>&1', $add_out, $add_code);
    if ($add_code !== 0) {
        chdir($cwd);
        return ['success' => false, 'message' => 'git add gagal: ' . implode(', ', $add_out)];
    }

    // Cek apakah ada yang perlu di-commit
    exec('git diff --cached --quiet 2>&1', $diff_out, $diff_code);
    if ($diff_code === 0) {
        chdir($cwd);
        return ['success' => true, 'message' => 'Tidak ada perubahan foto untuk di-backup'];
    }

    // Commit
    $msg = 'backup: photo upload ' . date('Y-m-d H:i:s');
    $escaped_msg = str_replace('"', '\\"', $msg);
    exec("git commit -m \"$escaped_msg\" 2>&1", $commit_out, $commit_code);
    if ($commit_code !== 0) {
        chdir($cwd);
        return ['success' => true, 'message' => 'Tidak ada perubahan baru untuk di-commit'];
    }

    // Set remote dengan token autentikasi
    $auth_url = str_replace('https://', "https://x-access-token:$git_token@", $repo_url);
    exec("git remote set-url origin \"$auth_url\" 2>&1", $remote_out, $remote_code);

    // Push ke branch
    $escaped_branch = escapeshellarg($branch);
    exec("git push $escaped_branch 2>&1", $push_out, $push_code);
    $push_output = implode(', ', $push_out);

    // Reset remote URL ke semula (tetap dijalankan meskipun push gagal)
    exec("git remote set-url origin \"$repo_url\" 2>&1");

    chdir($cwd);

    if ($push_code === 0) {
        return ['success' => true, 'message' => 'Foto berhasil di-backup ke git'];
    } else {
        return ['success' => false, 'message' => 'git push gagal: ' . $push_output];
    }
}
