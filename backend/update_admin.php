<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');
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
    $admins[] = [
        'id'            => generateAdminId(),
        'username'      => $username,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'role'          => $role,
        'nama'          => $nama ?: $username,
        'created_at'    => date('Y-m-d'),
    ];
    saveAdmins($admins);
    logAdminHistory('tambah_admin', 'admin', $id_new, 'Menambahkan admin: ' . ($_POST['nama'] ?? $_POST['username']));
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

    logAdminHistory('edit_admin', 'admin', $_POST['id'] ?? '', 'Mengedit admin: ' . ($_POST['username'] ?? ''));
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

// -------------------------------------------------------
// ACTION: get_schedules / add_schedule / delete_schedule
// -------------------------------------------------------
if ($action === 'get_schedules') {
    $schedules = loadSchedules();
    echo json_encode(['success'=>true,'data'=>$schedules]);
    exit;
}

if ($action === 'set_manual_status') {
    $status = ($_POST['status'] ?? 'buka') === 'tutup' ? 'tutup' : 'buka';
    if (saveStatus($status)) {
        logAdminHistory('update_status', 'status_toko', '', 'Mengubah status toko menjadi: ' . ($_POST['status'] ?? ''));
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH STATUS] ' . $push['message']);
echo json_encode(['success'=>true,'message'=>'Status manual berhasil disimpan.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Gagal menyimpan status.']);
    }
    exit;
}

if ($action === 'add_schedule') {
    $start_date = trim($_POST['start_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '00:00');
    $end_date   = trim($_POST['end_date'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? '00:00');
    $note       = trim($_POST['note'] ?? '');

    if (empty($start_date) || empty($end_date)) {
        echo json_encode(['success'=>false,'message'=>'Tanggal mulai dan selesai wajib diisi.']); exit;
    }

    $start = date('Y-m-d H:i', strtotime($start_date . ' ' . $start_time));
    $end   = date('Y-m-d H:i', strtotime($end_date . ' ' . $end_time));

    if (!$start || !$end || $start > $end) {
        echo json_encode(['success'=>false,'message'=>'Rentang waktu tidak valid.']); exit;
    }

    $schedules = loadSchedules();
    $schedules[] = [
        'id' => uniqid('s_'),
        'start' => $start,
        'end' => $end,
        'note' => $note,
        'created_at' => date('Y-m-d H:i')
    ];

    if (saveSchedules($schedules)) {
        logAdminHistory('add_schedule', 'jadwal_tutup', '', 'Menambahkan jadwal tutup sementara');
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH SCHEDULE] ' . $push['message']);
echo json_encode(['success'=>true,'message'=>'Jadwal tutup sementara berhasil ditambahkan.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Gagal menyimpan jadwal.']);
    }
    exit;
}

if ($action === 'edit_schedule') {
    $id = $_POST['id'] ?? '';
    $start_date = trim($_POST['start_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '00:00');
    $end_date   = trim($_POST['end_date'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? '00:00');
    $note       = trim($_POST['note'] ?? '');

    if (empty($id) || empty($start_date) || empty($end_date)) {
        echo json_encode(['success'=>false,'message'=>'Data tidak lengkap.']); exit;
    }
    $start = date('Y-m-d H:i', strtotime($start_date . ' ' . $start_time));
    $end   = date('Y-m-d H:i', strtotime($end_date . ' ' . $end_time));
    if (!$start || !$end || $start > $end) {
        echo json_encode(['success'=>false,'message'=>'Rentang waktu tidak valid.']); exit;
    }
    $schedules = loadSchedules();
    $found = false;
    foreach ($schedules as &$s) {
        if (($s['id'] ?? '') === $id) {
            $s['start'] = $start;
            $s['end'] = $end;
            $s['note'] = $note;
            $found = true;
            break;
        }
    }
    unset($s);
    if (!$found) { echo json_encode(['success'=>false,'message'=>'Jadwal tidak ditemukan.']); exit; }
    if (saveSchedules($schedules)) {
        logAdminHistory('edit_schedule', 'jadwal_tutup', '', 'Mengedit jadwal tutup sementara');
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH SCHEDULE] ' . $push['message']);
        echo json_encode(['success'=>true,'message'=>'Jadwal berhasil diperbarui.']);
    }
    else echo json_encode(['success'=>false,'message'=>'Gagal menyimpan jadwal.']);
    exit;
}

if ($action === 'delete_schedule') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) { echo json_encode(['success'=>false,'message'=>'ID jadwal tidak ditemukan.']); exit; }
    $schedules = array_values(array_filter(loadSchedules(), function($s) use($id){ return ($s['id'] ?? '') !== $id; }));
    if (saveSchedules($schedules)) {
        logAdminHistory('delete_schedule', 'jadwal_tutup', $id ?? '', 'Menghapus jadwal tutup sementara');
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH SCHEDULE] ' . $push['message']);
        echo json_encode(['success'=>true,'message'=>'Jadwal berhasil dihapus.']);
    }
    else echo json_encode(['success'=>false,'message'=>'Gagal menghapus jadwal.']);
    exit;
}

// -------------------------------------------------------
// ACTION: save_heading / get_heading
// -------------------------------------------------------
if ($action === 'save_heading') {
    $prefix = trim($_POST['prefix'] ?? '');
    $brand  = trim($_POST['brand'] ?? '');
    if (empty($prefix) || empty($brand)) {
        echo json_encode(['success'=>false,'message'=>'Prefix dan brand heading tidak boleh kosong.']);
        exit;
    }
    if (saveHeading($prefix, $brand)) {
        logAdminHistory('update_heading', 'heading', '', 'Mengupdate heading toko');
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH HEADING] ' . $push['message']);
echo json_encode(['success'=>true,'message'=>'Heading toko berhasil disimpan.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Gagal menyimpan heading toko.']);
    }
    exit;
}

if ($action === 'get_heading') {
    $heading = loadHeading();
    echo json_encode(['success'=>true, 'data' => $heading]);
    exit;
}

// -------------------------------------------------------
// ACTION: save_tagline / get_tagline
// -------------------------------------------------------
// -------------------------------------------------------
// ACTION: save_product_info / get_product_info
// -------------------------------------------------------
if ($action === 'save_product_info') {
    $text = trim($_POST['text'] ?? '');
    if (empty($text)) {
        echo json_encode(['success'=>false,'message'=>'Teks tidak boleh kosong.']);
        exit;
    }
    if (saveProductInfoText($text)) {
        logAdminHistory('update_product_info', 'product_info', '', 'Mengupdate info produk di halaman toko');
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH PRODINFO] ' . $push['message']);
echo json_encode(['success'=>true,'message'=>'Teks info produk berhasil disimpan.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Gagal menyimpan teks info produk.']);
    }
    exit;
}

if ($action === 'get_product_info') {
    echo json_encode(['success'=>true, 'data' => ['text' => loadProductInfoText()]]);
    exit;
}

if ($action === 'save_tagline') {
    $tagline = trim($_POST['tagline'] ?? '');
    if (empty($tagline)) {
        echo json_encode(['success'=>false,'message'=>'Tagline tidak boleh kosong.']);
        exit;
    }
    if (saveTagline($tagline)) {
        logAdminHistory('update_tagline', 'tagline', '', 'Mengupdate tagline toko');
        $push = backupToGit();
        if (!$push['success']) error_log('[PUSH TAGLINE] ' . $push['message']);
echo json_encode(['success'=>true,'message'=>'Tagline berhasil disimpan.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Gagal menyimpan tagline.']);
    }
    exit;
}

if ($action === 'get_tagline') {
    echo json_encode(['success'=>true, 'data' => ['tagline' => loadTagline()]]);
    exit;
}


// ============================================================
// SEARCH SERIAL NUMBER — Cari nota pembelian & penjualan via serial number
// ============================================================
if ($action === 'search_serial') {
    $db = getDB();
    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database tidak tersedia.']);
        exit;
    }

    $query = trim($_POST['query'] ?? '');
    if (empty($query)) {
        echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
        exit;
    }

    $search = pg_escape_string($db, $query);

    $sql = "SELECT
                s.noserial,
                s.kodeitem,
                i.namaitem,
                i.serial AS item_pakai_serial,
                s.stsada,
                s.notrsm AS notrans_beli,
                s.notrsrk AS notrans_jual,
                im.tanggal AS tgl_beli,
                ik.tanggal AS tgl_jual,
                sp_beli.nama AS nama_supplier,
                sp_beli.kode AS kode_supplier,
                sp_jual.nama AS nama_pelanggan,
                sp_jual.kode AS kode_pelanggan,
                im.totalakhir AS total_beli,
                ik.totalakhir AS total_jual,
                s.dateupd
            FROM tbl_itemserial s
            LEFT JOIN tbl_item i ON s.kodeitem = i.kodeitem
            LEFT JOIN tbl_imhd im ON s.notrsm = im.notransaksi
            LEFT JOIN tbl_ikhd ik ON s.notrsrk = ik.notransaksi
            LEFT JOIN tbl_supel sp_beli ON im.kodesupel = sp_beli.kode
            LEFT JOIN tbl_supel sp_jual ON ik.kodesupel = sp_jual.kode
            WHERE s.noserial ILIKE '%$search%'
               OR s.kodeitem ILIKE '%$search%'
               OR i.namaitem ILIKE '%$search%'
            ORDER BY s.dateupd DESC
            LIMIT 100";

    $result = @pg_query($db, $sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query gagal: ' . pg_last_error($db)]);
        exit;
    }

    $data = [];
    while ($row = pg_fetch_assoc($result)) {
        $data[] = $row;
    }

    // Count total
    $count_sql = "SELECT COUNT(*) AS total FROM tbl_itemserial s
                  LEFT JOIN tbl_item i ON s.kodeitem = i.kodeitem
                  WHERE s.noserial ILIKE '%$search%'
                     OR s.kodeitem ILIKE '%$search%'
                     OR i.namaitem ILIKE '%$search%'";
    $count_result = @pg_query($db, $count_sql);
    $total = $count_result ? (int)pg_fetch_result($count_result, 0, 'total') : 0;

    echo json_encode(['success' => true, 'data' => $data, 'total' => $total]);
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
    
    $result = @pg_query($db, "SELECT id, admin_id, admin_username, admin_nama, action, target_type, target_id, detail, to_char(created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at FROM admin_history ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
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

// -------------------------------------------------------
// ACTION: push_to_git
// -------------------------------------------------------
if ($action === 'push_to_git') {
    requireLogin();
    $result = backupToGit();
    echo json_encode($result);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Action tidak dikenali.']);