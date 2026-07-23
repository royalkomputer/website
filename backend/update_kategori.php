<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

try {

require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'get') {
    $data = loadKategori();
    $existing = [];
    foreach ($data['kategori'] as $k) {
        $existing[$k['id']] = $k;
    }
    $newCats = detectNewKategori($existing);
    echo json_encode([
        'success' => true,
        'data' => $data['kategori'],
        'new' => $newCats,
        'hidden' => loadHiddenProducts(),
        'promo' => loadPromo(),
    ]);
    exit;
}

if ($action === 'simpan') {
    $kategori = $_POST['kategori'] ?? '';
    $parsed = json_decode($kategori, true);
    if (!is_array($parsed)) {
        echo json_encode(['success' => false, 'message' => 'Format data kategori tidak valid.']);
        exit;
    }
    $data = ['kategori' => $parsed];
    saveKategori($data);
    echo json_encode(['success' => true, 'message' => 'Kategori berhasil disimpan.']);
    exit;
}

if ($action === 'toggle_produk') {
    $id = trim($_POST['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID produk tidak valid.']);
        exit;
    }
    $hidden = loadHiddenProducts();
    $idx = array_search($id, $hidden);
    if ($idx !== false) {
        array_splice($hidden, $idx, 1);
        $msg = 'Produk kini tampil di marketplace.';
    } else {
        $hidden[] = $id;
        $msg = 'Produk disembunyikan dari marketplace.';
    }
    saveHiddenProducts($hidden);
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

if ($action === 'simpan_promo') {
    $id = trim($_POST['id'] ?? '');
    $harga_coret = (int) ($_POST['harga_coret'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID produk tidak valid.']);
        exit;
    }
    $promo = loadPromo();
    if ($harga_coret > 0) {
        $promo[$id] = ['harga_coret' => $harga_coret];
        if ($label !== '') {
            $promo[$id]['label'] = $label;
        }
    } else {
        unset($promo[$id]);
    }
    savePromo($promo);
    echo json_encode(['success' => true, 'message' => $harga_coret > 0 ? 'Promo berhasil disimpan.' : 'Promo dihapus.']);
    exit;
}

if ($action === 'hapus_promo') {
    $id = trim($_POST['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID produk tidak valid.']);
        exit;
    }
    $promo = loadPromo();
    unset($promo[$id]);
    savePromo($promo);
    echo json_encode(['success' => true, 'message' => 'Promo dihapus.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
