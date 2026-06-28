<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

$action = $_POST['action'] ?? '';

/**
 * Generate a unique playlist ID.
 */
function newPlaylistId(): string {
    return 'pl_' . uniqid();
}

/**
 * Generate a unique photo filename for a playlist photo.
 */
function photoFilename(string $playlistId, int $index, string $ext = 'webp'): string {
    return $playlistId . '_' . $index . '.' . $ext;
}

/**
 * Upload and save a photo file, returns the saved filename.
 */
function uploadPhotoFile(array $file, string $playlistId, int $photoIndex): string|false {
    $target_dir = __DIR__ . '/uploads/banners/';
    @mkdir($target_dir, 0777, true);

    $ext = 'webp';
    $safe_name = photoFilename($playlistId, $photoIndex, $ext);
    $target_file = $target_dir . $safe_name;

    // Move to a temp location, then process
    $tmp = $target_dir . 'tmp_' . uniqid();
    if (!move_uploaded_file($file['tmp_name'], $tmp)) {
        return false;
    }

    // Convert to WebP + resize if GD available
    if (function_exists('gdWebpAvailable') && gdWebpAvailable()) {
        $img = createImageFromFile($tmp);
        if ($img) {
            // Resize large images (max 1920px wide, 1080px tall)
            $origW = imagesx($img);
            $origH = imagesy($img);
            $maxW = 1920;
            $maxH = 1080;
            if ($origW > $maxW || $origH > $maxH) {
                $ratio = min($maxW / $origW, $maxH / $origH);
                $newW = (int)round($origW * $ratio);
                $newH = (int)round($origH * $ratio);
                $resized = imagecreatetruecolor($newW, $newH);
                if ($resized) {
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagedestroy($img);
                    $img = $resized;
                }
            }
            if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($img);
            if (function_exists('imagealphablending')) @imagealphablending($img, true);
            if (function_exists('imagesavealpha')) @imagesavealpha($img, true);
            @imagewebp($img, $target_file, 85);
            @imagedestroy($img);
        }
    }

    // Fallback: copy as-is if WebP output missing (no GD or conversion failed)
    if (!file_exists($target_file)) {
        copy($tmp, $target_file);
    }

    @unlink($tmp);

    // Copy to frontend
    @mkdir(FE_DIR . '/uploads/banners/', 0777, true);
    @copy($target_file, FE_DIR . '/uploads/banners/' . $safe_name);

    return $safe_name;
}

/**
 * Delete old photo files.
 */
function deletePhotoFiles(string $filename): void {
    $target_dir = __DIR__ . '/uploads/banners/';
    $backend_file = $target_dir . $filename;
    if (file_exists($backend_file)) @unlink($backend_file);
    $fe_file = FE_DIR . '/uploads/banners/' . $filename;
    if (file_exists($fe_file)) @unlink($fe_file);
}

// ============================================================
// ACTION: save_playlist — Create or update a playlist
// ============================================================
if ($action === 'save_playlist') {
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $interval = (int)($_POST['interval'] ?? 5000);
    $aspect = trim($_POST['aspect'] ?? '16/9');
    $active = ($_POST['active'] ?? '1') === '1';
    $order = (int)($_POST['order'] ?? 0);

    $playlists = loadBanners();
    $isNew = empty($id);

    if ($isNew) {
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Nama playlist tidak boleh kosong.']);
            exit;
        }
        $id = newPlaylistId();
        $playlist = [
            'id' => $id,
            'name' => $name,
            'order' => count($playlists) + 1,
            'active' => $active,
            'interval' => max(2000, $interval),
            'aspect' => $aspect,
            'photos' => []
        ];
        $playlists[] = &$playlist;
    } else {
        $idx = -1;
        foreach ($playlists as $i => $p) {
            if ($p['id'] === $id) { $idx = $i; break; }
        }
        if ($idx === -1) {
            echo json_encode(['success' => false, 'message' => 'Playlist tidak ditemukan.']);
            exit;
        }
        $playlist = &$playlists[$idx];
        // Only update fields that are provided
        if ($name !== '') {
            $playlist['name'] = $name;
        }
        $playlist['active'] = $active;
        $playlist['interval'] = max(2000, $interval);
        $playlist['aspect'] = $aspect;
        if ($order > 0 && $order !== ($playlist['order'] ?? 0)) {
            $playlist['order'] = $order;
        }
    }

    // Handle uploaded photos (multiple files via 'photos[]')
    if (!empty($_FILES['photos'])) {
        $files = $_FILES['photos'];
        $uploadedCount = is_array($files['name']) ? count($files['name']) : 1;
        
        if (!is_array($files['name'])) {
            // Single file upload
            if ($files['error'] === UPLOAD_ERR_OK) {
                $photoIndex = count($playlist['photos']);
                $saved = uploadPhotoFile([
                    'tmp_name' => $files['tmp_name'],
                    'name' => $files['name'],
                    'error' => $files['error']
                ], $id, $photoIndex);
                if ($saved) {
                    $link = trim($_POST['photo_link'] ?? '');
                    $alt = trim($_POST['photo_alt'] ?? '');
                    $playlist['photos'][] = ['image' => $saved, 'link' => $link, 'alt' => $alt];
                }
            }
        } else {
            // Multiple files upload
            for ($i = 0; $i < $uploadedCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $photoIndex = count($playlist['photos']);
                $saved = uploadPhotoFile([
                    'tmp_name' => $files['tmp_name'][$i],
                    'name' => $files['name'][$i],
                    'error' => $files['error'][$i]
                ], $id, $photoIndex);
                if ($saved) {
                    $playlist['photos'][] = ['image' => $saved, 'link' => '', 'alt' => ''];
                }
            }
        }
    }

    saveBanners($playlists);
    echo json_encode(['success' => true, 'message' => 'Playlist berhasil disimpan.', 'id' => $id]);
    exit;
}

// ============================================================
// ACTION: delete_playlist_photo — Delete a single photo from a playlist
// ============================================================
if ($action === 'delete_playlist_photo') {
    $playlistId = trim($_POST['playlist_id'] ?? '');
    $photoIndex = (int)($_POST['photo_index'] ?? -1);

    if (!$playlistId || $photoIndex < 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
        exit;
    }

    $playlists = loadBanners();
    $idx = -1;
    foreach ($playlists as $i => $p) {
        if ($p['id'] === $playlistId) { $idx = $i; break; }
    }
    if ($idx === -1) {
        echo json_encode(['success' => false, 'message' => 'Playlist tidak ditemukan.']);
        exit;
    }

    $photos = &$playlists[$idx]['photos'];
    if (!isset($photos[$photoIndex])) {
        echo json_encode(['success' => false, 'message' => 'Foto tidak ditemukan.']);
        exit;
    }

    // Delete the file
    if (!empty($photos[$photoIndex]['image'])) {
        deletePhotoFiles($photos[$photoIndex]['image']);
    }

    // Remove from array
    array_splice($photos, $photoIndex, 1);

    // Renumber remaining photos to match naming convention
    foreach ($photos as $pi => &$photo) {
        $oldName = $photo['image'];
        $newName = photoFilename($playlistId, $pi);
        if ($oldName !== $newName) {
            // Rename file
            $target_dir = __DIR__ . '/uploads/banners/';
            if (file_exists($target_dir . $oldName)) {
                rename($target_dir . $oldName, $target_dir . $newName);
            }
            $fe_dir = FE_DIR . '/uploads/banners/';
            if (file_exists($fe_dir . $oldName)) {
                rename($fe_dir . $oldName, $fe_dir . $newName);
            }
            $photo['image'] = $newName;
        }
    }
    unset($photo);

    saveBanners($playlists);
    echo json_encode(['success' => true, 'message' => 'Foto berhasil dihapus.']);
    exit;
}

// ============================================================
// ACTION: reorder_playlist_photos — Reorder photos within a playlist
// ============================================================
if ($action === 'reorder_playlist_photos') {
    $playlistId = trim($_POST['playlist_id'] ?? '');
    $order_raw = trim($_POST['order'] ?? '');
    $newOrder = json_decode($order_raw, true);

    if (!$playlistId || !is_array($newOrder)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
        exit;
    }

    $playlists = loadBanners();
    $idx = -1;
    foreach ($playlists as $i => $p) {
        if ($p['id'] === $playlistId) { $idx = $i; break; }
    }
    if ($idx === -1) {
        echo json_encode(['success' => false, 'message' => 'Playlist tidak ditemukan.']);
        exit;
    }

    $photos = &$playlists[$idx]['photos'];
    $reordered = [];
    foreach ($newOrder as $oi) {
        if (isset($photos[$oi])) {
            $reordered[] = $photos[$oi];
        }
    }

    // Rename files to match new indices — two-pass to avoid collisions
    $target_dir = __DIR__ . '/uploads/banners/';
    $fe_dir = FE_DIR . '/uploads/banners/';
    // Pass 1: rename all to temp names
    foreach ($reordered as $pi => &$photo) {
        $oldName = $photo['image'];
        $newName = photoFilename($playlistId, $pi);
        if ($oldName !== $newName) {
            $tmpName = $playlistId . '_' . $pi . '.reorder_tmp';
            if (file_exists($target_dir . $oldName)) {
                rename($target_dir . $oldName, $target_dir . $tmpName);
            }
            if (file_exists($fe_dir . $oldName)) {
                rename($fe_dir . $oldName, $fe_dir . $tmpName);
            }
            $photo['image'] = $tmpName;
        }
    }
    // Pass 2: rename temps to final names
    foreach ($reordered as $pi => &$photo) {
        $oldName = $photo['image'];
        $newName = photoFilename($playlistId, $pi);
        if ($oldName !== $newName) {
            if (file_exists($target_dir . $oldName)) {
                rename($target_dir . $oldName, $target_dir . $newName);
            }
            if (file_exists($fe_dir . $oldName)) {
                rename($fe_dir . $oldName, $fe_dir . $newName);
            }
            $photo['image'] = $newName;
        }
    }
    unset($photo);

    $playlists[$idx]['photos'] = $reordered;
    saveBanners($playlists);
    echo json_encode(['success' => true, 'message' => 'Urutan foto berhasil disimpan.']);
    exit;
}

// ============================================================
// ACTION: update_photo_info — Update link/alt for a photo
// ============================================================
if ($action === 'update_photo_info') {
    $playlistId = trim($_POST['playlist_id'] ?? '');
    $photoIndex = (int)($_POST['photo_index'] ?? -1);
    $link = trim($_POST['link'] ?? '');
    $alt = trim($_POST['alt'] ?? '');

    if (!$playlistId || $photoIndex < 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
        exit;
    }

    $playlists = loadBanners();
    $idx = -1;
    foreach ($playlists as $i => $p) {
        if ($p['id'] === $playlistId) { $idx = $i; break; }
    }
    if ($idx === -1) {
        echo json_encode(['success' => false, 'message' => 'Playlist tidak ditemukan.']);
        exit;
    }

    if (!isset($playlists[$idx]['photos'][$photoIndex])) {
        echo json_encode(['success' => false, 'message' => 'Foto tidak ditemukan.']);
        exit;
    }

    $playlists[$idx]['photos'][$photoIndex]['link'] = $link;
    $playlists[$idx]['photos'][$photoIndex]['alt'] = $alt;
    saveBanners($playlists);
    echo json_encode(['success' => true, 'message' => 'Info foto berhasil diperbarui.']);
    exit;
}

// ============================================================
// ACTION: delete_playlist — Delete entire playlist
// ============================================================
if ($action === 'delete_playlist') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID playlist diperlukan.']);
        exit;
    }

    $playlists = loadBanners();
    $deleted = false;

    foreach ($playlists as $i => $p) {
        if ($p['id'] === $id) {
            // Delete all photo files
            if (!empty($p['photos'])) {
                foreach ($p['photos'] as $photo) {
                    if (!empty($photo['image'])) {
                        deletePhotoFiles($photo['image']);
                    }
                }
            }
            array_splice($playlists, $i, 1);
            $deleted = true;
            break;
        }
    }

    if (!$deleted) {
        echo json_encode(['success' => false, 'message' => 'Playlist tidak ditemukan.']);
        exit;
    }

    // Reorder
    foreach ($playlists as $i => &$p) $p['order'] = $i + 1;
    unset($p);

    saveBanners($playlists);
    echo json_encode(['success' => true, 'message' => 'Playlist berhasil dihapus.']);
    exit;
}

// ============================================================
// ACTION: reorder_playlists — Reorder playlists
// ============================================================
if ($action === 'reorder_playlists') {
    $ids_raw = trim($_POST['ids'] ?? '');
    $ids = json_decode($ids_raw, true);
    if (!is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'Format data tidak valid.']);
        exit;
    }

    $playlists = loadBanners();
    $ordered = [];
    foreach ($ids as $order => $id) {
        foreach ($playlists as &$p) {
            if ($p['id'] === $id) {
                $p['order'] = $order + 1;
                $ordered[] = $p;
                break;
            }
        }
    }
    unset($p);

    saveBanners($ordered);
    echo json_encode(['success' => true, 'message' => 'Urutan playlist berhasil disimpan.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
