<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

$action = $_POST['action'] ?? '';

if ($action === 'upload') {
    $id = trim($_POST['id'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $alt = trim($_POST['alt'] ?? '');
    $order = (int)($_POST['order'] ?? 0);
    $active = ($_POST['active'] ?? '1') === '1';

    $banners = loadBanners();

    if ($id) {
        // Update existing banner
        $idx = -1;
        foreach ($banners as $i => $b) {
            if ($b['id'] === $id) { $idx = $i; break; }
        }
        if ($idx === -1) {
            echo json_encode(['success' => false, 'message' => 'Banner tidak ditemukan.']);
            exit;
        }
        $banner = &$banners[$idx];
    } else {
        // New banner
        $id = 'bnr_' . uniqid();
        $banner = ['id' => $id, 'image' => '', 'link' => '', 'alt' => '', 'order' => count($banners) + 1, 'active' => true];
        $banners[] = &$banner;
    }

    // Handle file upload
    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . '/uploads/banners/';
        @mkdir($target_dir, 0777, true);
        $safe_name = $id . '.webp';
        $target_file = $target_dir . $safe_name;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            echo json_encode(['success' => false, 'message' => 'Gagal upload file.']);
            exit;
        }

        // Optimize WEBP if GD available
        if (function_exists('gdWebpAvailable') && gdWebpAvailable()) {
            $img = createImageFromFile($target_file);
            if ($img) {
                @imagewebp($img, $target_file, 85);
                @imagedestroy($img);
            }
        }

        // Copy to frontend
        @mkdir(FE_DIR . '/uploads/banners/', 0777, true);
        @copy($target_file, FE_DIR . '/uploads/banners/' . $safe_name);

        // Delete old image if replacing
        if (!empty($banner['image']) && $banner['image'] !== $safe_name) {
            $old_file = $target_dir . $banner['image'];
            if (file_exists($old_file)) @unlink($old_file);
            $fe_old = FE_DIR . '/uploads/banners/' . $banner['image'];
            if (file_exists($fe_old)) @unlink($fe_old);
        }

        $banner['image'] = $safe_name;
    }

    $banner['link'] = $link;
    $banner['alt'] = $alt;
    $banner['active'] = $active;

    if ($order > 0 && $order !== $banner['order']) {
        $banner['order'] = $order;
    }

    saveBanners($banners);
    echo json_encode(['success' => true, 'message' => 'Banner berhasil disimpan.', 'id' => $id]);
    exit;
}

if ($action === 'delete') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID banner diperlukan.']);
        exit;
    }

    $banners = loadBanners();
    $deleted = false;
    $target_dir = __DIR__ . '/uploads/banners/';

    foreach ($banners as $i => $b) {
        if ($b['id'] === $id) {
            // Delete image files
            if (!empty($b['image'])) {
                $backend_file = $target_dir . $b['image'];
                if (file_exists($backend_file)) @unlink($backend_file);
                $fe_file = FE_DIR . '/uploads/banners/' . $b['image'];
                if (file_exists($fe_file)) @unlink($fe_file);
            }
            array_splice($banners, $i, 1);
            $deleted = true;
            break;
        }
    }

    if (!$deleted) {
        echo json_encode(['success' => false, 'message' => 'Banner tidak ditemukan.']);
        exit;
    }

    // Reorder
    foreach ($banners as $i => &$b) $b['order'] = $i + 1;
    unset($b);

    saveBanners($banners);
    echo json_encode(['success' => true, 'message' => 'Banner berhasil dihapus.']);
    exit;
}

if ($action === 'reorder') {
    $ids_raw = trim($_POST['ids'] ?? '');
    $ids = json_decode($ids_raw, true);
    if (!is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'Format data tidak valid.']);
        exit;
    }

    $banners = loadBanners();
    $ordered = [];
    foreach ($ids as $order => $id) {
        foreach ($banners as &$b) {
            if ($b['id'] === $id) {
                $b['order'] = $order + 1;
                $ordered[] = $b;
                break;
            }
        }
    }
    unset($b);

    saveBanners($ordered);
    echo json_encode(['success' => true, 'message' => 'Urutan banner berhasil disimpan.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
