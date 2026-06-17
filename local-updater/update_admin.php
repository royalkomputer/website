<?php
header('Content-Type: application/json');
require_once 'config.php';

requireLogin();

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
    if (file_put_contents(STATUS_FILE, $status) !== false) {
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
    if (saveSchedules($schedules)) echo json_encode(['success'=>true,'message'=>'Jadwal berhasil diperbarui.']);
    else echo json_encode(['success'=>false,'message'=>'Gagal menyimpan jadwal.']);
    exit;
}

if ($action === 'delete_schedule') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) { echo json_encode(['success'=>false,'message'=>'ID jadwal tidak ditemukan.']); exit; }
    $schedules = array_values(array_filter(loadSchedules(), function($s) use($id){ return ($s['id'] ?? '') !== $id; }));
    if (saveSchedules($schedules)) echo json_encode(['success'=>true,'message'=>'Jadwal berhasil dihapus.']);
    else echo json_encode(['success'=>false,'message'=>'Gagal menghapus jadwal.']);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Action tidak dikenali.']);