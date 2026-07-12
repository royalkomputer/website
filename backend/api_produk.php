<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once 'config.php';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, min(100, intval($_GET['limit'] ?? 12)));
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$sort = trim($_GET['sort'] ?? 'default');

$cache_file = __DIR__ . '/data/cache_produk.json';

if (file_exists($cache_file)) {
    $all_produk = json_decode(file_get_contents($cache_file), true);
    if (!is_array($all_produk)) $all_produk = [];
    $all_produk = array_values(array_filter($all_produk, fn($p) =>
        stripos($p['name'] ?? '', 'pesanan') === false &&
        stripos($p['name'] ?? '', 'jasa') === false
    ));
} else {
    $all_produk = [];
}

// Admin request: return raw cache as plain array for client-side filtering
if (isset($_GET['admin'])) {
    $all_produk = array_map(function ($p) {
        if (!empty($p['image']) && strpos($p['image'], 'uploads/') === 0) {
            $p['image'] = '/' . $p['image'];
        }
        if (!empty($p['images']) && is_array($p['images'])) {
            $p['images'] = array_map(function ($img) {
                return strpos($img, 'uploads/') === 0 ? '/' . $img : $img;
            }, $p['images']);
        }
        return $p;
    }, $all_produk);
    echo json_encode($all_produk);
    exit;
}

// Filter
$filtered = [];
foreach ($all_produk as $p) {
    if ($category !== '' && ($p['category'] ?? '') !== $category) continue;
    if ($search !== '' && stripos($p['name'] ?? '', $search) === false) continue;
    $isBekas = stripos($p['name'] ?? '', '2ND') !== false;
    if ($condition === 'baru' && $isBekas) continue;
    if ($condition === 'bekas' && !$isBekas) continue;
    $filtered[] = $p;
}

$total = count($filtered);

// Sort
if ($sort === 'low-high') {
    usort($filtered, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
} elseif ($sort === 'high-low') {
    usort($filtered, fn($a, $b) => ($b['price'] ?? 0) <=> ($a['price'] ?? 0));
}

// Paginate
$offset = ($page - 1) * $limit;
$page_produk = array_slice($filtered, $offset, $limit);

// Process images for current page
// Di VPS: frontend Nginx serve /uploads/ langsung dari volume bersama
$upload_dir = __DIR__ . "/uploads/";

foreach ($page_produk as &$p) {
    if (empty($p['id'])) continue;
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $p['id']);
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
        foreach ($matched_files as $file) {
            $images[] = "/uploads/" . basename($file) . "?v=" . filemtime($file);
        }
        $p['image'] = $images[0];
        $p['images'] = $images;
    } elseif (empty($p['image']) || strpos($p['image'], 'unsplash') !== false) {
        $default_img = "https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500";
        $p['image'] = $default_img;
        $p['images'] = [$default_img];
    }
}
unset($p);

// Compute categories from full cache (unfiltered)
$catMap = [];
foreach ($all_produk as $p) {
    $c = trim($p['category'] ?? '');
    if ($c === '') $c = 'Lainnya';
    $catMap[$c] = ($catMap[$c] ?? 0) + 1;
}
ksort($catMap);

echo json_encode([
    "data" => $page_produk,
    "total" => $total,
    "page" => $page,
    "limit" => $limit,
    "categories" => $catMap
]);
exit;

// ── DB fallback (if cache unavailable) ──
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(["data" => [], "total" => 0, "page" => $page, "limit" => $limit, "error" => "Koneksi database gagal"]);
    exit;
}

$check_table = "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_web_deskripsi');";
$res_table = pg_query($conn, $check_table);
$table_exists = pg_fetch_result($res_table, 0, 0);

if ($table_exists == 'f') {
    pg_query($conn, "CREATE TABLE tbl_web_deskripsi (kodeitem VARCHAR(50) PRIMARY KEY, deskripsi TEXT);");
}

$sql = "SELECT i.kodeitem AS id, i.namaitem AS name, i.jenis AS category, i.hargajual1 AS price,
            COALESCE(s.total_stok, 0) AS stock, COALESCE(w.deskripsi, '') AS description
        FROM tbl_item i
        INNER JOIN (SELECT kodeitem, SUM(stok) as total_stok FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0) s ON i.kodeitem = s.kodeitem
        LEFT JOIN tbl_web_deskripsi w ON i.kodeitem = w.kodeitem
        WHERE LOWER(i.namaitem) NOT LIKE '%pesanan%'
          AND LOWER(i.namaitem) NOT LIKE '%jasa%'";

$result = @pg_query($conn, $sql);
if (!$result) {
    echo json_encode(["data" => [], "total" => 0, "page" => $page, "limit" => $limit, "error" => pg_last_error($conn)]);
    exit;
}

$db_produk = [];
while ($row = pg_fetch_assoc($result)) {
    $row['price'] = (float) $row['price'];
    $row['stock'] = (float) $row['stock'];
    if (empty(trim($row['category']))) $row['category'] = 'Lainnya';
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $row['id']);
    $images = [];
    $matched_files = [];
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $matches = glob($upload_dir . $safe_kode . "_*." . $ext);
        if ($matches) $matched_files = array_merge($matched_files, $matches);
    }
    sort($matched_files);
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $legacy_file = $upload_dir . $safe_kode . "." . $ext;
        if (file_exists($legacy_file)) { array_unshift($matched_files, $legacy_file); break; }
    }
    if (!empty($matched_files)) {
        $images = [];
        foreach ($matched_files as $file) {
            $images[] = "/uploads/" . basename($file) . "?v=" . filemtime($file);
        }
        $row['image'] = $images[0]; $row['images'] = $images;
    } else {
        $d = "https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500";
        $row['image'] = $d; $row['images'] = [$d];
    }
    $db_produk[] = $row;
}

file_put_contents($cache_file, json_encode($db_produk));

// Apply same filters/pagination on DB results
$filtered = [];
foreach ($db_produk as $p) {
    if ($category !== '' && ($p['category'] ?? '') !== $category) continue;
    if ($search !== '' && stripos($p['name'] ?? '', $search) === false) continue;
    $isBekas = stripos($p['name'] ?? '', '2ND') !== false;
    if ($condition === 'baru' && $isBekas) continue;
    if ($condition === 'bekas' && !$isBekas) continue;
    $filtered[] = $p;
}
$total = count($filtered);
if ($sort === 'low-high') usort($filtered, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
elseif ($sort === 'high-low') usort($filtered, fn($a, $b) => ($b['price'] ?? 0) <=> ($a['price'] ?? 0));
$page_produk = array_slice($filtered, ($page - 1) * $limit, $limit);

echo json_encode(["data" => $page_produk, "total" => $total, "page" => $page, "limit" => $limit]);
pg_close($conn);
