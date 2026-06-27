<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once 'config.php';

$cache_file = __DIR__ . '/data/cache_produk.json';

// Baca dari cache sebagai sumber data utama
if (file_exists($cache_file)) {
    $cache_data = file_get_contents($cache_file);
    $produk = json_decode($cache_data, true);

    // Refresh foto dari filesystem (update timestamp cachebuster)
    if (is_array($produk)) {
        foreach ($produk as &$p) {
            if (isset($p['id'])) {
                $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $p['id']);
                $upload_dir = __DIR__ . "/uploads/";
                $images = [];
                $matched_files = [];
                foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
                    $matches = glob($upload_dir . $safe_kode . "_*." . $ext);
                    if ($matches) $matched_files = array_merge($matched_files, $matches);
                }
                sort($matched_files);
                foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
                    $legacy_file = $upload_dir . $safe_kode . "." . $ext;
                    if (file_exists($legacy_file)) {
                        array_unshift($matched_files, $legacy_file);
                        break;
                    }
                }
                if (!empty($matched_files)) {
                    $images = [];
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $img_base = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'royal-backend-s3ir.onrender.com');
                    foreach ($matched_files as $file) {
                        $images[] = $img_base . "/uploads/" . basename($file) . "?v=" . filemtime($file);
                    }
                    $p['image'] = $images[0];
                    $p['images'] = $images;
                } elseif (empty($p['image']) || strpos($p['image'], 'unsplash') !== false) {
                    $default_img = "https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500";
                    $p['image'] = $default_img;
                    $p['images'] = [$default_img];
                }
            }
        }
        unset($p);
        echo json_encode($produk);
        exit;
    }
}

// Jika cache tidak ada, coba dari database
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(["error" => "Koneksi database gagal dan tidak ada data cache yang tersedia."]);
    exit;
}

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

$sql = "SELECT i.kodeitem AS id, i.namaitem AS name, i.jenis AS category, i.hargajual1 AS price, 
            COALESCE(s.total_stok, 0) AS stock,
            COALESCE(w.deskripsi, '') AS description
        FROM tbl_item i
        INNER JOIN (
            SELECT kodeitem, SUM(stok) as total_stok 
            FROM tbl_itemstok 
            GROUP BY kodeitem 
            HAVING SUM(stok) > 0
        ) s ON i.kodeitem = s.kodeitem
        LEFT JOIN tbl_web_deskripsi w ON i.kodeitem = w.kodeitem";

$result = @pg_query($conn, $sql);

if (!$result) {
    $error_msg = pg_last_error($conn);
    echo json_encode(["error" => "Query gagal dijalankan.", "details" => $error_msg]);
    exit;
}

$produk = [];
$query_has_results = false;
while($row = pg_fetch_assoc($result)) {
    $query_has_results = true;
    $row['price'] = (float) $row['price'];
    $row['stock'] = (float) $row['stock'];
    if (empty(trim($row['category']))) $row['category'] = 'Lainnya';

    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $row['id']);

    $upload_dir = __DIR__ . "/uploads/";
    $images = [];
    $matched_files = [];
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $matches = glob($upload_dir . $safe_kode . "_*." . $ext);
        if ($matches) $matched_files = array_merge($matched_files, $matches);
    }
    sort($matched_files);

    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $legacy_file = $upload_dir . $safe_kode . "." . $ext;
        if (file_exists($legacy_file)) {
            array_unshift($matched_files, $legacy_file);
            break;
        }
    }

    if (!empty($matched_files)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $img_base = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'royal-backend-s3ir.onrender.com');
        foreach ($matched_files as $file) {
            $images[] = $img_base . "/uploads/" . basename($file) . "?v=" . filemtime($file);
        }
        $row['image'] = $images[0];
        $row['images'] = $images;
    } else {
        $default_img = "https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500";
        $row['image'] = $default_img;
        $row['images'] = [$default_img];
    }

    $produk[] = $row;
}

if (!$query_has_results) {
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
        pg_close($conn);
        exit;
    }
}

file_put_contents($cache_file, json_encode($produk));

echo json_encode($produk);
pg_close($conn);
