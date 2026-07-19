<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

try {

require_once 'config.php';

// Check auth: return JSON error instead of redirect (consistent with other API endpoints)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

$action       = $_POST['action'] ?? '';
$current      = getCurrentAdmin();
$is_super     = isSuperAdmin();

// -------------------------------------------------------
// ACTION: tambah_admin (super admin only)
// -------------------------------------------------------
if ($action === 'tambah_admin') {
    if (!$is_super) { echo json_encode(['success'=>false,'message'=>'Akses ditolak.']); exit; }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nama     = trim($_POST['nama'] ?? '');
    $role     = $_POST['role'] ?? 'admin';

    if (strlen($username) < 3) { echo json_encode(['success'=>false,'message'=>'Username minimal 3 karakter.']); exit; }
    if (strlen($password) < 6) { echo json_encode(['success'=>false,'message'=>'Password minimal 6 karakter.']); exit; }
    if (!in_array($role, ['admin','super_admin'])) $role = 'admin';
    if (findAdminByUsername($username)) { echo json_encode(['success'=>false,'message'=>'Username sudah digunakan.']); exit; }

    $admins   = loadAdmins();
    $new_id   = generateAdminId();
    $admins[] = [
        'id'            => $new_id,
        'username'      => $username,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'role'          => $role,
        'nama'          => $nama ?: $username,
        'created_at'    => date('Y-m-d'),
    ];
    saveAdmins($admins);
    logAdminHistory('tambah_admin', 'admin', $new_id, 'Menambahkan admin: ' . ($_POST['nama'] ?? $_POST['username']));
    echo json_encode(['success'=>true,'message'=>'Admin baru berhasil ditambahkan.']);
    exit;
}

// -------------------------------------------------------
// ACTION: hapus_admin (super admin only)
// -------------------------------------------------------
if ($action === 'hapus_admin') {
    if (!$is_super) { echo json_encode(['success'=>false,'message'=>'Akses ditolak.']); exit; }

    $target_id = $_POST['target_id'] ?? '';
    if ($target_id === $current['id']) { echo json_encode(['success'=>false,'message'=>'Tidak bisa menghapus akun sendiri.']); exit; }

    $admins = array_values(array_filter(loadAdmins(), fn($a) => $a['id'] !== $target_id));

    // Pastikan minimal 1 super admin tetap ada
    $super_count = count(array_filter($admins, fn($a) => $a['role'] === 'super_admin'));
    if ($super_count < 1) { echo json_encode(['success'=>false,'message'=>'Harus ada minimal 1 super admin.']); exit; }

    saveAdmins($admins);
    logAdminHistory('hapus_admin', 'admin', $_POST['target_id'] ?? '', 'Menghapus admin ID: ' . ($_POST['target_id'] ?? ''));
echo json_encode(['success'=>true,'message'=>'Admin berhasil dihapus.']);
    exit;
}

// -------------------------------------------------------
// ACTION: edit_admin
// Super admin: bisa edit siapa saja (username, password, nama, role)
// Admin biasa: hanya bisa edit dirinya sendiri (username, password, nama) - TIDAK bisa ganti role
// -------------------------------------------------------
if ($action === 'edit_admin') {
    $target_id = $_POST['target_id'] ?? '';
    $target    = findAdminById($target_id);

    if (!$target) { echo json_encode(['success'=>false,'message'=>'Admin tidak ditemukan.']); exit; }

    // Admin biasa hanya boleh edit dirinya sendiri
    if (!$is_super && $target_id !== $current['id']) {
        echo json_encode(['success'=>false,'message'=>'Akses ditolak. Anda hanya bisa mengedit akun sendiri.']);
        exit;
    }

    $admins      = loadAdmins();
    $new_username = trim($_POST['username'] ?? $target['username']);
    $new_nama     = trim($_POST['nama']     ?? $target['nama']);
    $new_password = $_POST['password']      ?? '';
    $new_role     = $is_super ? ($_POST['role'] ?? $target['role']) : $target['role']; // Admin biasa tidak bisa ganti role

    if (strlen($new_username) < 3) { echo json_encode(['success'=>false,'message'=>'Username minimal 3 karakter.']); exit; }
    if ($new_password && strlen($new_password) < 6) { echo json_encode(['success'=>false,'message'=>'Password minimal 6 karakter.']); exit; }

    // Cek username duplikat (kecuali milik sendiri)
    foreach ($admins as $a) {
        if ($a['username'] === $new_username && $a['id'] !== $target_id) {
            echo json_encode(['success'=>false,'message'=>'Username sudah digunakan admin lain.']); exit;
        }
    }

    // Cegah super admin terakhir didegradasi
    if ($is_super && $new_role === 'admin' && $target['role'] === 'super_admin') {
        $super_count = count(array_filter($admins, fn($a) => $a['role'] === 'super_admin'));
        if ($super_count <= 1) {
            echo json_encode(['success'=>false,'message'=>'Harus ada minimal 1 super admin.']); exit;
        }
    }

    foreach ($admins as &$a) {
        if ($a['id'] === $target_id) {
            $a['username'] = $new_username;
            $a['nama']     = $new_nama;
            $a['role']     = $new_role;
            if (!empty($new_password)) {
                $a['password_hash'] = password_hash($new_password, PASSWORD_BCRYPT);
            }
            break;
        }
    }
    unset($a);

    saveAdmins($admins);

    // Jika admin mengedit dirinya sendiri, update session username
    if ($target_id === $current['id']) {
        $_SESSION['admin_username'] = $new_username;
    }

    logAdminHistory('edit_admin', 'admin', $_POST['target_id'] ?? '', 'Mengedit admin: ' . ($_POST['username'] ?? ''));
echo json_encode(['success'=>true,'message'=>'Data admin berhasil diperbarui.']);
    exit;
}

// -------------------------------------------------------
// ACTION: get_admins (super admin only)
// -------------------------------------------------------
if ($action === 'get_admins') {
    if (!$is_super) { echo json_encode(['success'=>false,'message'=>'Akses ditolak.']); exit; }
    $admins = array_map(function($a) {
        unset($a['password_hash']); // Jangan kirim hash ke frontend
        return $a;
    }, loadAdmins());
    echo json_encode(['success'=>true,'data'=>$admins]);
    exit;
}




// ============================================================
// SCHEDULE MANAGEMENT
// ============================================================

if ($action === 'get_schedules') {
    $schedules = loadSchedules();
    echo json_encode(['success' => true, 'data' => $schedules]);
    exit;
}

if ($action === 'add_schedule') {
    $start_date = trim($_POST['start_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '00:00');
    $end_date   = trim($_POST['end_date'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? '23:59');
    $note       = trim($_POST['note'] ?? '');

    if (!$start_date || !$end_date) {
        echo json_encode(['success' => false, 'message' => 'Tanggal harus diisi.']);
        exit;
    }

    $start = $start_date . ' ' . $start_time;
    $end   = $end_date . ' ' . $end_time;

    if ($start > $end) {
        echo json_encode(['success' => false, 'message' => 'Waktu mulai harus sebelum waktu selesai.']);
        exit;
    }

    $schedules = loadSchedules();
    $new_schedule = [
        'id'         => 's_' . uniqid(),
        'start'      => $start,
        'end'        => $end,
        'note'       => $note,
        'created_at' => date('Y-m-d H:i'),
    ];
    $schedules[] = $new_schedule;
    saveSchedules($schedules);

    logAdminHistory('add_schedule', 'schedule', $new_schedule['id'], 'Menambahkan jadwal tutup: ' . $note);
    echo json_encode(['success' => true, 'message' => 'Jadwal berhasil ditambahkan.']);
    exit;
}

if ($action === 'edit_schedule') {
    $id         = trim($_POST['id'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '00:00');
    $end_date   = trim($_POST['end_date'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? '23:59');
    $note       = trim($_POST['note'] ?? '');

    if (!$id || !$start_date || !$end_date) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
        exit;
    }

    $start = $start_date . ' ' . $start_time;
    $end   = $end_date . ' ' . $end_time;

    if ($start > $end) {
        echo json_encode(['success' => false, 'message' => 'Waktu mulai harus sebelum waktu selesai.']);
        exit;
    }

    $schedules = loadSchedules();
    $found = false;
    foreach ($schedules as &$s) {
        if ($s['id'] === $id) {
            $s['start'] = $start;
            $s['end']   = $end;
            $s['note']  = $note;
            $found = true;
            break;
        }
    }
    unset($s);

    if (!$found) {
        echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
        exit;
    }

    saveSchedules($schedules);
    logAdminHistory('edit_schedule', 'schedule', $id, 'Mengedit jadwal tutup: ' . $note);
    echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui.']);
    exit;
}

if ($action === 'delete_schedule') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID jadwal diperlukan.']);
        exit;
    }

    $schedules = loadSchedules();
    $filtered = array_values(array_filter($schedules, fn($s) => $s['id'] !== $id));

    if (count($filtered) === count($schedules)) {
        echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
        exit;
    }

    saveSchedules($filtered);
    logAdminHistory('delete_schedule', 'schedule', $id, 'Menghapus jadwal tutup');
    echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus.']);
    exit;
}

// ============================================================
// MANUAL STATUS TOGGLE
// ============================================================

if ($action === 'set_manual_status') {
    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, ['buka', 'tutup'])) {
        echo json_encode(['success' => false, 'message' => 'Status harus "buka" atau "tutup".']);
        exit;
    }

    saveStatus($status);
    logAdminHistory('set_manual_status', 'status', $status, 'Mengubah status toko manual: ' . $status);
    echo json_encode(['success' => true, 'message' => 'Status toko berhasil diubah.', 'status' => $status]);
    exit;
}

// ============================================================
// GET ADMIN HISTORY
// ============================================================
if ($action === 'get_history') {
    $db = getDB();
    if (!$db) {
        echo json_encode(['success'=>false,'message'=>'Database tidak tersedia.']);
        exit;
    }
    
    $limit = (int)($_POST['limit'] ?? 50);
    $offset = (int)($_POST['offset'] ?? 0);
    
    $result = @pg_query_params($db, "SELECT id, admin_id, admin_username, admin_nama, action, target_type, target_id, detail, to_char(created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at FROM admin_history ORDER BY created_at DESC LIMIT $1 OFFSET $2", array($limit, $offset));
    if (!$result) {
        echo json_encode(['success'=>false,'message'=>'Gagal mengambil history.']);
        exit;
    }
    
    $history = [];
    while ($row = pg_fetch_assoc($result)) {
        $history[] = $row;
    }
    
    $count_result = @pg_query($db, "SELECT COUNT(*) AS total FROM admin_history");
    $total = $count_result ? (int)pg_fetch_result($count_result, 0, 'total') : 0;
    
    echo json_encode(['success'=>true, 'data' => $history, 'total' => $total]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Action tidak dikenali.']);

} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}