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
    
    if (is_file($filepath) && strpos(realpath($filepath), realpath($target_dir)) === 0 && strpos(basename($filepath), $safe_kode) === 0) {
        unlink($filepath);
        echo json_encode(["success" => true, "message" => "Foto berhasil dihapus."]);
        exit;
    }
    echo json_encode(["success" => false, "message" => "File tidak ditemukan atau akses ditolak."]);
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

    echo json_encode(["success" => true, "message" => "Urutan foto berhasil disimpan."]);
    exit;
}

echo json_encode(["success" => false, "message" => "Aksi tidak dikenal."]);
?>
