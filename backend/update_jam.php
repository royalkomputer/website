<?php
header('Content-Type: application/json');
require_once 'config.php';

requireLogin();

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

    // Validasi format HH:MM
    if ($buka  && !preg_match('/^\d{2}:\d{2}$/', $buka))  { echo json_encode(['success'=>false,'message'=>"Format jam $hari tidak valid."]); exit; }
    if ($tutup && !preg_match('/^\d{2}:\d{2}$/', $tutup)) { echo json_encode(['success'=>false,'message'=>"Format jam $hari tidak valid."]); exit; }

    if ($buka)  $jam_baru[$hari]['buka']  = $buka;
    if ($tutup) $jam_baru[$hari]['tutup'] = $tutup;
}

if (file_put_contents(JAM_FILE, json_encode($jam_baru, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file jam operasional.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Jam operasional berhasil disimpan.']);