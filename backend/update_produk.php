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
$db_available = ($conn !== false);

if ($db_available) {
    // --- FITUR AUTO-CREATE TABEL ---
    $check_table = "SELECT EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_name = 'tbl_web_deskripsi'
    );";
    $res_table = pg_query($conn, $check_table);
    $table_exists = pg_fetch_result($res_table, 0, 0);

    if ($table_exists == 'f') {
        $create_sql = "CREATE TABLE tbl_web_deskripsi (
            kodeitem VARCHAR(50) PRIMARY KEY,
            deskripsi TEXT
        );";
        pg_query($conn, $create_sql);
    }
}

$id = $_POST['id'] ?? '';
$description = $_POST['description'] ?? '';

if (empty($id)) {
    echo json_encode(["success" => false, "message" => "ID Item tidak valid."]);
    exit;
}

// 1. PROSES UPDATE ATAU INSERT DESKRIPSI KE DATABASE
if ($db_available) {
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
}

// 2. PROSES UPLOAD FOTO — AUTO-KONVERSI KE WEBP
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
    $target_dir = __DIR__ . "/uploads/";
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $image_extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif'];
    $existing_files = [];
    foreach ($image_extensions as $ext) {
        $matches = glob($target_dir . $safe_kode . "_*." . $ext);
        if ($matches) {
            $existing_files = array_merge($existing_files, $matches);
        }
    }
    sort($existing_files);

    foreach ($image_extensions as $ext) {
        $legacy_file = $target_dir . $safe_kode . "." . $ext;
        if (file_exists($legacy_file)) {
            array_unshift($existing_files, $legacy_file);
            break;
        }
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

    while ($uploadFiles && isset($uploadFiles['tmp_name'][$newIndex]) && $uploadFiles['error'][$newIndex] === UPLOAD_ERR_OK) {
        $orderedItems[] = [
            'type' => 'new',
            'tmp_name' => $uploadFiles['tmp_name'][$newIndex],
            'name' => $uploadFiles['name'][$newIndex],
        ];
        $newIndex++;
    }

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

    $tempPaths = [];
    foreach ($orderedItems as $item) {
        if ($item['type'] === 'existing' && !isset($tempPaths[$item['basename']]) && file_exists($item['path'])) {
            $ext = pathinfo($item['path'], PATHINFO_EXTENSION);
            $tempPath = $target_dir . 'temp_' . uniqid() . '.' . $ext;
            rename($item['path'], $tempPath);
            $tempPaths[$item['basename']] = $tempPath;
        }
    }

    $savedFiles = [];
    $index = 1;
    foreach ($orderedItems as $item) {
        $target_file = $target_dir . $safe_kode . '_' . $index . '.webp';
        $savedFiles[] = basename($target_file);
        if ($item['type'] === 'existing') {
            if (isset($tempPaths[$item['basename']])) {
                $tempPath = $tempPaths[$item['basename']];
                $ext = pathinfo($tempPath, PATHINFO_EXTENSION);
                if ($ext === 'webp') {
                    rename($tempPath, $target_file);
                    touch($target_file);
                } else {
                    $img = null;
                    $mime = image_type_to_mime_type(exif_imagetype($tempPath));
                    switch ($mime) {
                        case 'image/jpeg': $img = imagecreatefromjpeg($tempPath); break;
                        case 'image/png':
                            $img = imagecreatefrompng($tempPath);
                            imagepalettetotruecolor($img);
                            imagealphablending($img, true);
                            imagesavealpha($img, true);
                            break;
                        case 'image/gif': $img = imagecreatefromgif($tempPath); break;
                    }
                    if ($img) {
                        imagewebp($img, $target_file, 85);
                        imagedestroy($img);
                    }
                    unlink($tempPath);
                }
            }
        } elseif ($item['type'] === 'new') {
            $file_tmp = $item['tmp_name'];
            $img_info = getimagesize($file_tmp);
            if ($img_info) {
                $mime = $img_info['mime'];
                $image = null;
                switch ($mime) {
                    case 'image/jpeg': $image = imagecreatefromjpeg($file_tmp); break;
                    case 'image/png':
                        $image = imagecreatefrompng($file_tmp);
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                        break;
                    case 'image/webp': $image = imagecreatefromwebp($file_tmp); break;
                    case 'image/gif': $image = imagecreatefromgif($file_tmp); break;
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

    $all_old = [];
    foreach ($image_extensions as $ext) {
        $old = glob($target_dir . $safe_kode . "_*." . $ext);
        if ($old) $all_old = array_merge($all_old, $old);
    }
    foreach ($image_extensions as $ext) {
        $legacy = $target_dir . $safe_kode . "." . $ext;
        if (file_exists($legacy)) $all_old[] = $legacy;
    }
    foreach ($all_old as $file) {
        if (!in_array(basename($file), $savedFiles) && file_exists($file)) {
            unlink($file);
        }
    }

    // --- FRONTEND SYNC: Copy updated photos to frontend/uploads/ for immediate sync ---
    $frontend_upload_dir = __DIR__ . '/../frontend/uploads/';
    if (!is_dir($frontend_upload_dir)) {
        @mkdir($frontend_upload_dir, 0777, true);
    }
    
    // Copy all saved photos for this product to frontend
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $saved_photos = glob($target_dir . $safe_kode . '_*.' . $ext);
        if ($saved_photos) {
            foreach ($saved_photos as $photo) {
                copy($photo, $frontend_upload_dir . basename($photo));
            }
        }
        // Copy legacy file (safe_kode.webp) if exists
        $legacy = $target_dir . $safe_kode . '.' . $ext;
        if (file_exists($legacy)) {
            copy($legacy, $frontend_upload_dir . $safe_kode . '.' . $ext);
        }
    }
    
    // Update cache files with correct photo URLs and description
    $cache_files = [
        __DIR__ . '/data/cache_produk.json',   // backend (dibaca admin)
        __DIR__ . '/../frontend/cache_produk.json',  // frontend (dibaca user)
    ];
    foreach ($cache_files as $cache_file) {
        if (file_exists($cache_file)) {
            $cacheData = json_decode(file_get_contents($cache_file), true);
            if (is_array($cacheData)) {
                foreach ($cacheData as &$entry) {
                    if (isset($entry['id']) && preg_replace('/[^A-Za-z0-9]/', '_', $entry['id']) === $safe_kode) {
                        // Update photo URLs
                        $newImages = [];
                        $matched_files = [];
                        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
                            $m = glob($frontend_upload_dir . $safe_kode . '_*.' . $ext);
                            if ($m) $matched_files = array_merge($matched_files, $m);
                        }
                        sort($matched_files);
                        if (!empty($matched_files)) {
                            foreach ($matched_files as $file) {
                                $newImages[] = 'uploads/' . basename($file) . '?v=' . filemtime($file);
                            }
                            $entry['image'] = $newImages[0];
                            $entry['images'] = $newImages;
                        }
                        break;
                    }
                }
                unset($entry);
                file_put_contents($cache_file, json_encode($cacheData));
            }
        }
    }
}

// Simpan deskripsi ke cache jika DB tidak tersedia (sebagai fallback)
$desc_provided = !empty($description);
if (!$db_available && $desc_provided) {
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $id);
    $cache_files = [
        __DIR__ . '/data/cache_produk.json',
        __DIR__ . '/../frontend/cache_produk.json',
    ];
    foreach ($cache_files as $cache_file) {
        if (file_exists($cache_file)) {
            $cacheData = json_decode(file_get_contents($cache_file), true);
            if (is_array($cacheData)) {
                foreach ($cacheData as &$entry) {
                    if (isset($entry['id']) && preg_replace('/[^A-Za-z0-9]/', '_', $entry['id']) === $safe_kode) {
                        $entry['description'] = $description;
                        break;
                    }
                }
                unset($entry);
                file_put_contents($cache_file, json_encode($cacheData));
            }
        }
    }
}

if ($db_available) {
    pg_close($conn);
}

if (!$db_available && $desc_provided) {
    echo json_encode(["success" => true, "warning" => true, "message" => "Foto dan deskripsi berhasil disimpan (offline mode). Database tidak terhubung, data disimpan di cache."]);
} else {
    echo json_encode(["success" => true, "message" => "Item berhasil diperbarui."]);
}
?>