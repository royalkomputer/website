<?php
header('Content-Type: application/json');
require_once 'config.php';

// Check auth: return JSON error instead of redirect (consistent with other API endpoints)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

// Hanya super admin boleh mengubah jam operasional
if (!isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Super Admin yang dapat mengubah jam operasional.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']);
    exit;
}

$hari_valid = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$jam_lama   = loadJamOperasional();
$jam_baru   = $jam_lama;

foreach ($hari_valid as $hari) {
    $buka  = $_POST['buka_'  . $hari] ?? null;
    $tutup = $_POST['tutup_' . $hari] ?? null;
    $libur = isset($_POST['libur_' . $hari]) ? true : false;

    if ($libur) {
        // Hari libur — kosongkan jam buka/tutup
        $jam_baru[$hari]['buka']  = '';
        $jam_baru[$hari]['tutup'] = '';
        $jam_baru[$hari]['libur'] = true;
    } else {
        $jam_baru[$hari]['libur'] = false;

        // Validasi format HH:MM
        if (!$buka || !preg_match('/^\d{2}:\d{2}$/', $buka)) {
            echo json_encode(['success'=>false,'message'=>"Format jam buka $hari tidak valid."]);
            exit;
        }
        if (!$tutup || !preg_match('/^\d{2}:\d{2}$/', $tutup)) {
            echo json_encode(['success'=>false,'message'=>"Format jam tutup $hari tidak valid."]);
            exit;
        }

        $jam_baru[$hari]['buka']  = $buka;
        $jam_baru[$hari]['tutup'] = $tutup;
    }
}

if (file_put_contents(JAM_FILE, json_encode($jam_baru, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file jam operasional.']);
    exit;
}

// Sync ke frontend untuk immediate update
@file_put_contents(__DIR__ . '/../frontend/jam_operasional.json', json_encode($jam_baru, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'Jam operasional berhasil disimpan.']);