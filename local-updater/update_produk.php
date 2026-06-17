<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

require_once 'config.php';

// --- PROTEKSI SESSION: Hanya admin yang sudah login yang bisa akses ---
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Gagal terhubung ke database."]);
    exit;
}

// --- FITUR AUTO-CREATE TABEL ---
// Pengecekan apakah tabel sudah ada
$check_table = "SELECT EXISTS (
    SELECT 1 FROM information_schema.tables 
    WHERE table_name = 'tbl_web_deskripsi'
);";
$res_table = pg_query($conn, $check_table);
$table_exists = pg_fetch_result($res_table, 0, 0);

if ($table_exists == 'f') {
    // Tabel belum ada, buat otomatis!
    $create_sql = "CREATE TABLE tbl_web_deskripsi (
        kodeitem VARCHAR(50) PRIMARY KEY,
        deskripsi TEXT
    );";
    pg_query($conn, $create_sql);
}

$id = $_POST['id'] ?? '';
$description = $_POST['description'] ?? '';

if (empty($id)) {
    echo json_encode(["success" => false, "message" => "ID Item tidak valid."]);
    exit;
}

// 1. PROSES UPDATE ATAU INSERT DESKRIPSI KE DATABASE
$check_sql = "SELECT kodeitem FROM tbl_web_deskripsi WHERE kodeitem = $1";
$check_res = pg_query_params($conn, $check_sql, array($id));

if (pg_num_rows($check_res) > 0) {
    $update_sql = "UPDATE tbl_web_deskripsi SET deskripsi = $1 WHERE kodeitem = $2";
    $result = pg_query_params($conn, $update_sql, array($description, $id));
} else {
    $insert_sql = "INSERT INTO tbl_web_deskripsi (kodeitem, deskripsi) VALUES ($1, $2)";
    $result = pg_query_params($conn, $insert_sql, array($id, $description));
}

if (!$result) {
    echo json_encode(["success" => false, "message" => "Gagal menyimpan rincian deskripsi ke database."]);
    exit;
}

// 2. PROSES UPLOAD FOTO DAN KONVERSI OTOMATIS KE FORMAT WEBP
$uploadFiles = $_FILES['new_files'] ?? $_FILES['foto'] ?? null;
$imageOrder = [];
if (isset($_POST['image_order'])) {
    $decodedOrder = json_decode($_POST['image_order'], true);
    if (is_array($decodedOrder)) {
        $imageOrder = $decodedOrder;
    }
}

if ($uploadFiles || !empty($imageOrder)) {
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $id);
    $target_dir = "uploads/";
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $existing_files = glob($target_dir . $safe_kode . "_*.webp");
    $legacy_file = $target_dir . $safe_kode . ".webp";
    if (file_exists($legacy_file)) {
        array_unshift($existing_files, $legacy_file);
    }

    $existing_map = [];
    foreach ($existing_files as $file) {
        $existing_map[basename($file)] = $file;
    }

    $orderedItems = [];
    $newIndex = 0;
    $newCount = 0;
    if ($uploadFiles && isset($uploadFiles['tmp_name'])) {
        $newCount = is_array($uploadFiles['tmp_name']) ? count($uploadFiles['tmp_name']) : 0;
    }

    if (!empty($imageOrder)) {
        foreach ($imageOrder as $token) {
            if (!is_string($token)) continue;
            if (preg_match('/^new_\d+$/', $token)) {
                if ($uploadFiles && isset($uploadFiles['tmp_name'][$newIndex]) && $uploadFiles['error'][$newIndex] === UPLOAD_ERR_OK) {
                    $orderedItems[] = [
                        'type' => 'new',
                        'tmp_name' => $uploadFiles['tmp_name'][$newIndex],
                        'name' => $uploadFiles['name'][$newIndex],
                    ];
                }
                $newIndex++;
            } else {
                $basename = basename(parse_url($token, PHP_URL_PATH));
                if (isset($existing_map[$basename])) {
                    $orderedItems[] = [
                        'type' => 'existing',
                        'path' => $existing_map[$basename],
                        'basename' => $basename,
                    ];
                }
            }
        }
    }

    // Append any remaining new files that weren't referenced explicitly
    while ($uploadFiles && isset($uploadFiles['tmp_name'][$newIndex]) && $uploadFiles['error'][$newIndex] === UPLOAD_ERR_OK) {
        $orderedItems[] = [
            'type' => 'new',
            'tmp_name' => $uploadFiles['tmp_name'][$newIndex],
            'name' => $uploadFiles['name'][$newIndex],
        ];
        $newIndex++;
    }

    // If no image order given, keep existing files and append new files at the end
    if (empty($imageOrder)) {
        foreach ($existing_files as $file) {
            $orderedItems[] = [
                'type' => 'existing',
                'path' => $file,
                'basename' => basename($file),
            ];
        }
        if ($uploadFiles) {
            for ($i = 0; $i < $newCount; $i++) {
                if ($uploadFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $orderedItems[] = [
                        'type' => 'new',
                        'tmp_name' => $uploadFiles['tmp_name'][$i],
                        'name' => $uploadFiles['name'][$i],
                    ];
                }
            }
        }
    }

    // Move existing files to temporary names to avoid overwrite collisions
    $tempPaths = [];
    foreach ($orderedItems as $item) {
        if ($item['type'] === 'existing' && !isset($tempPaths[$item['basename']]) && file_exists($item['path'])) {
            $tempPath = $target_dir . 'temp_' . uniqid() . '.webp';
            rename($item['path'], $tempPath);
            $tempPaths[$item['basename']] = $tempPath;
        }
    }

    $index = 1;
    foreach ($orderedItems as $item) {
        $target_file = $target_dir . $safe_kode . '_' . $index . '.webp';
        if ($item['type'] === 'existing') {
            if (isset($tempPaths[$item['basename']])) {
                rename($tempPaths[$item['basename']], $target_file);
            }
        } elseif ($item['type'] === 'new') {
            $file_tmp = $item['tmp_name'];
            $img_info = getimagesize($file_tmp);
            if ($img_info) {
                $mime = $img_info['mime'];
                switch ($mime) {
                    case 'image/jpeg': $image = imagecreatefromjpeg($file_tmp); break;
                    case 'image/png': 
                        $image = imagecreatefrompng($file_tmp);
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                        break;
                    case 'image/webp': $image = imagecreatefromwebp($file_tmp); break;
                    default: $image = null;
                }
                if ($image) {
                    imagewebp($image, $target_file, 85);
                    imagedestroy($image);
                } else {
                    move_uploaded_file($file_tmp, $target_file);
                }
            }
        }
        $index++;
    }

    // Remove any old files that were not included in the current order
    foreach ($existing_files as $file) {
        $basename = basename($file);
        $shouldKeep = false;
        foreach ($orderedItems as $item) {
            if ($item['type'] === 'existing' && $item['basename'] === $basename) {
                $shouldKeep = true;
                break;
            }
        }
        if (!$shouldKeep && file_exists($file)) {
            unlink($file);
        }
    }
}

echo json_encode(["success" => true, "message" => "Item berhasil diperbarui."]);
pg_close($conn);
?>