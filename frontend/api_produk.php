<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$cache_file = __DIR__ . '/cache_produk.json';

// Baca dari cache sebagai sumber data utama
if (file_exists($cache_file)) {
    $cache_data = file_get_contents($cache_file);
    $produk = json_decode($cache_data, true);

    if (is_array($produk)) {
        $produk = array_values(array_filter($produk, fn($p) =>
            stripos($p['name'] ?? '', 'pesanan') === false &&
            stripos($p['name'] ?? '', 'jasa') === false
        ));

        // Muat konfigurasi kategori + hidden + promo
        $kategori_config = [];
        $kategori_file = __DIR__ . '/../backend/data/kategori.json';
        if (file_exists($kategori_file)) {
            $raw = json_decode(file_get_contents($kategori_file), true);
            foreach (($raw['kategori'] ?? []) as $k) {
                $kategori_config[$k['id']] = $k;
            }
        }

        $hidden_ids = [];
        $hidden_file = __DIR__ . '/../backend/data/produk_tersembunyi.json';
        if (file_exists($hidden_file)) {
            $hidden_ids = json_decode(file_get_contents($hidden_file), true) ?? [];
        }

        $promo_data = [];
        $promo_file = __DIR__ . '/../backend/data/produk_promo.json';
        if (file_exists($promo_file)) {
            $promo_data = json_decode(file_get_contents($promo_file), true) ?? [];
        }

        // Filter: hapus produk dari kategori tersembunyi + produk tersembunyi
        $produk = array_filter($produk, function($p) use ($kategori_config, $hidden_ids) {
            $cat = $p['category'] ?? '';
            $visible = $kategori_config[$cat]['visible'] ?? true;
            return $visible && !in_array($p['id'], $hidden_ids);
        });
        $produk = array_values($produk);

        // Di VPS: cukup /uploads/ karena frontend & backend satu domain
        foreach ($produk as &$p) {
            if (!empty($p['image']) && strpos($p['image'], 'uploads/') === 0) {
                $p['image'] = '/' . $p['image'];
            }
            if (!empty($p['images']) && is_array($p['images'])) {
                foreach ($p['images'] as &$img) {
                    if (strpos($img, 'uploads/') === 0) {
                        $img = '/' . $img;
                    }
                }
                unset($img);
            }
            // Sertakan data promo
            $id = $p['id'] ?? '';
            if (isset($promo_data[$id])) {
                $promo = $promo_data[$id];
                if (!empty($promo['harga_coret']) && $promo['harga_coret'] > $p['price']) {
                    $p['harga_coret'] = (int) $promo['harga_coret'];
                    if (!empty($promo['label'])) {
                        $p['label_promo'] = $promo['label'];
                    }
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
        LEFT JOIN tbl_web_deskripsi w ON i.kodeitem = w.kodeitem
        WHERE LOWER(i.namaitem) NOT LIKE '%pesanan%'
          AND LOWER(i.namaitem) NOT LIKE '%jasa%'";

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
    if (!is_dir($upload_dir)) {
        $backend_dir = __DIR__ . "/../backend/uploads/";
        if (is_dir($backend_dir)) $upload_dir = $backend_dir;
    }
    $images = [];
    $matched_files = glob($upload_dir . $safe_kode . "_*.webp");
    if (empty($matched_files)) {
        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($upload_dir . $safe_kode . "_*." . $ext);
            if ($matches) $matched_files = array_merge($matched_files, $matches);
        }
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
        foreach ($matched_files as $file) {
            $images[] = "/uploads/" . basename($file) . "?v=" . filemtime($file);
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
