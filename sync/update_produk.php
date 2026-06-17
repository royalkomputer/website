<?php
/**
 * sync/update_produk.php — Headless IPOS Sync Agent
 *
 * Runs on the local PC via Windows Task Scheduler every 1 hour.
 * Queries the IPOS PostgreSQL database for products with stock > 0,
 * generates cache_produk.json, and writes it to both sync/ and frontend/.
 *
 * After this script completes, git_push.bat commits and pushes changes.
 *
 * Usage: php sync/update_produk.php
 * Log:   sync/sync.log
 */

error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/config.php';

$log_file = __DIR__ . '/sync.log';
$timestamp = date('Y-m-d H:i:s');

function write_log(string $message): void {
    global $log_file, $timestamp;
    file_put_contents($log_file, "$timestamp $message\n", FILE_APPEND);
}

// --- KONEKSI DATABASE ---
$conn = getDBConnection();
if (!$conn) {
    write_log("FAIL — Could not connect to database");
    echo "Sync failed: DB connection error\n";
    exit(1);
}

// --- QUERY PRODUK DENGAN STOK > 0 ---
// Sama persis dengan query di api_produk.php
$sql = "SELECT i.kodeitem AS id, i.namaitem AS name, i.jenis AS category,
            i.hargajual1 AS price,
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
    write_log("FAIL — Query error: " . pg_last_error($conn));
    echo "Sync failed: query error\n";
    pg_close($conn);
    exit(1);
}

$produk = [];
while ($row = pg_fetch_assoc($result)) {
    $row['price'] = (float) $row['price'];
    $row['stock'] = (float) $row['stock'];
    if (empty(trim($row['category']))) $row['category'] = 'Lainnya';

    // --- GENERATE IMAGE PATHS ---
    // Photos live in frontend/uploads/, referenced from there
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $row['id']);
    $uploads_path = __DIR__ . '/../frontend/uploads/';
    $images = [];

    $matched_files = glob($uploads_path . $safe_kode . '_*.webp');
    $legacy_file = $uploads_path . $safe_kode . '.webp';
    if (file_exists($legacy_file)) {
        array_unshift($matched_files, $legacy_file);
    }

    if (!empty($matched_files)) {
        foreach ($matched_files as $file) {
            $images[] = 'uploads/' . basename($file) . '?v=' . filemtime($file);
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

pg_close($conn);

// --- WRITE CACHE FILES ---
// Write to sync/ (local reference)
$sync_cache = __DIR__ . '/cache_produk.json';
file_put_contents($sync_cache, json_encode($produk));

// Write to frontend/ (for Netlify/storefront deployment)
$frontend_cache = __DIR__ . '/../frontend/cache_produk.json';
file_put_contents($frontend_cache, json_encode($produk));

// --- LOG ---
$count = count($produk);
write_log("OK — $count products synced");
echo "Sync complete: $count products\n";
