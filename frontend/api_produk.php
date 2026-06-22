<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$conn = getDBConnection();

if (!$conn) {
    // Coba baca dari cache jika database mati
    $cache_file = __DIR__ . '/cache_produk.json';
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
        exit;
    }
    echo json_encode(["error" => "Koneksi database gagal dan tidak ada data cache yang tersedia."]);
    exit;
}

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

// Mengubah fallback tulisan 'Tidak ada deskripsi tersedia.' menjadi string kosong
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
        foreach ($matched_files as $file) {
            $images[] = "uploads/" . basename($file) . "?v=" . filemtime($file);
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

// If query returned no results (e.g., DB has the schema but no IPOS data),
// fall back to the cache file written by the sync agent
if (!$query_has_results) {
    $cache_file = __DIR__ . '/cache_produk.json';
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
        pg_close($conn);
        exit;
    }
}

// Simpan hasil terbaru ke dalam file cache untuk antisipasi database mati
file_put_contents(__DIR__ . '/cache_produk.json', json_encode($produk));

echo json_encode($produk);
pg_close($conn);
?>