<?php
// Mencegah PHP error dari merusak JSON output
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once 'config.php';

// Proteksi akses
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak."]);
    exit;
}

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(["success" => false, "message" => "ID Item tidak valid."]);
    exit;
}

$safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $id);
$target_dir = __DIR__ . "/uploads/";

if ($action === 'delete') {
    $file_url = $_POST['file'] ?? ''; 
    $path_parts = explode('?', $file_url);
    $filepath = __DIR__ . '/' . $path_parts[0];
    
    // Validasi path — gunakan dirname() agar realpath() tidak gagal jika file sudah tidak ada
    $target_real = realpath($target_dir);
    $dir_real    = realpath(dirname($filepath));
    if ($target_real !== false && $dir_real !== false
        && strpos($dir_real, $target_real) === 0
        && strpos(basename($filepath), $safe_kode) === 0
    ) {
        // Cek dulu apakah file ada
        $file_exists = is_file($filepath);

        if ($file_exists) {
            unlink($filepath);
        }

        // Juga hapus dari frontend/uploads/ agar user UI ikut terhapus
        $frontend_target = __DIR__ . '/../frontend/uploads/' . basename($filepath);
        if (is_file($frontend_target)) {
            unlink($frontend_target);
        }

        // Update cache agar user UI juga ikut terupdate
        foreach ([__DIR__ . '/data/cache_produk.json', __DIR__ . '/../frontend/cache_produk.json'] as $cacheFile) {
            if (file_exists($cacheFile)) {
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cacheData)) {
                    foreach ($cacheData as &$entry) {
                        if (isset($entry['id']) && preg_replace('/[^A-Za-z0-9]/', '_', $entry['id']) === $safe_kode) {
                            // Scan ulang file yang masih ada di backend uploads/
                            $newImages = [];
                            foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
                                $m = glob($target_dir . $safe_kode . '_*.' . $ext);
                                if ($m) $newImages = array_merge($newImages, $m);
                            }
                            sort($newImages);
                            $fallback = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500';
                            $newImages = array_map(fn($f) => 'uploads/' . basename($f) . '?v=' . filemtime($f), $newImages);
                            $entry['image'] = $newImages[0] ?? $fallback;
                            $entry['images'] = $newImages;
                            break;
                        }
                    }
                    unset($entry);
                    file_put_contents($cacheFile, json_encode($cacheData));
                }
            }
        }

        if ($file_exists) {
            echo json_encode(["success" => true, "message" => "Foto berhasil dihapus."]);
        } else {
            echo json_encode(["success" => true, "warning" => true, "message" => "Foto sudah tidak tersedia."]);
        }
        exit;
    }
    echo json_encode(["success" => false, "message" => "Akses ditolak: file tidak valid."]);
    exit;

} elseif ($action === 'reorder') {
    $files = json_decode($_POST['files'] ?? '[]', true);
    if (!is_array($files)) {
        echo json_encode(["success" => false, "message" => "Data urutan tidak valid."]);
        exit;
    }

    $temp_names = [];
    foreach ($files as $file_url) {
        $path_parts = explode('?', $file_url);
        $filepath = __DIR__ . '/' . $path_parts[0];
        if (is_file($filepath) && strpos(realpath($filepath), realpath($target_dir)) === 0) {
            $ext = pathinfo($filepath, PATHINFO_EXTENSION);
            $temp_name = $target_dir . "temp_" . uniqid() . "." . $ext;
            rename($filepath, $temp_name);
            $temp_names[] = $temp_name;
        }
    }

    $index = 1;
    foreach ($temp_names as $temp_name) {
        $final_name = $target_dir . $safe_kode . "_" . $index . ".webp";
        $ext = pathinfo($temp_name, PATHINFO_EXTENSION);
        if ($ext === 'webp') {
            rename($temp_name, $final_name);
            touch($final_name);
        } else {
            $img = null;
            $mime = image_type_to_mime_type(exif_imagetype($temp_name));
            switch ($mime) {
                case 'image/jpeg': $img = imagecreatefromjpeg($temp_name); break;
                case 'image/png':
                    $img = imagecreatefrompng($temp_name);
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                    break;
                case 'image/gif': $img = imagecreatefromgif($temp_name); break;
            }
            if ($img) {
                imagewebp($img, $final_name, 85);
                imagedestroy($img);
            }
            unlink($temp_name);
        }
        $index++;
    }

    // Update cache dengan urutan baru agar tidak stale
    $cacheFile = __DIR__ . '/data/cache_produk.json';
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cacheData)) {
            foreach ($cacheData as &$entry) {
                if (isset($entry['id']) && preg_replace('/[^A-Za-z0-9]/', '_', $entry['id']) === $safe_kode) {
                    $newImages = [];
                    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
                        $m = glob($target_dir . $safe_kode . '_*.' . $ext);
                        if ($m) $newImages = array_merge($newImages, $m);
                    }
                    sort($newImages);
                    $newImages = array_map(fn($f) => 'uploads/' . basename($f) . '?v=' . filemtime($f), $newImages);
                    $entry['image'] = $newImages[0] ?? $entry['image'];
                    $entry['images'] = $newImages;
                    break;
                }
            }
            unset($entry);
            file_put_contents($cacheFile, json_encode($cacheData));
        }
    }

    echo json_encode(["success" => true, "message" => "Urutan foto berhasil disimpan."]);
    exit;
}

echo json_encode(["success" => false, "message" => "Aksi tidak dikenal."]);
?>
