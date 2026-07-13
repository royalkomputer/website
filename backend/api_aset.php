<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

try {

require_once __DIR__ . '/cors.php';
handleCORS();

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

$cache_file = __DIR__ . '/data/cache_aset.json';
$cached_data = null;

// Try to load from cache first (in case DB is unavailable)
if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached && isset($cached['data'])) {
        $cached_data = $cached;
    }
}

$db = getDB();
$load_from_cache = false;

if (!$db) {
    if ($cached_data) {
        $load_from_cache = true;
    } else {
        echo json_encode(["success" => false, "message" => "Database tidak tersedia dan cache aset kosong."]);
        exit;
    }
}

$action = $_POST['action'] ?? 'summary';

// ─── CACHE HELPERS ─────────────────────────────────────────────────────
function getAsetFromCache(array $cached, string $action, array $params = []): array {
    $products = $cached['products'] ?? [];
    $grand_modal = $cached['grand_total_modal'] ?? 0;
    $grand_jual = $cached['grand_total_jual'] ?? 0;
    $data = $cached['data'] ?? [];
    $categories = $data['categories'] ?? [];

    if ($action === 'summary') {
        return [
            'success' => true,
            'data' => [
                'total_items' => $data['total_items'] ?? 0,
                'total_stok' => $data['total_stok'] ?? 0,
                'total_nilai_modal' => $data['total_nilai_modal'] ?? 0,
                'total_nilai_jual' => $data['total_nilai_jual'] ?? 0,
                'total_potensi_laba' => $data['total_potensi_laba'] ?? 0,
                'punya_hpp' => $data['punya_hpp'] ?? false,
                'items_tanpa_hpp' => $data['items_tanpa_hpp'] ?? 0,
                'breakdown' => $data['breakdown'] ?? [],
            ],
            'from_cache' => true,
            'synced_at' => $cached['synced_at'] ?? null,
        ];
    }

    if ($action === 'get_categories') {
        return [
            'success' => true,
            'data' => $categories,
            'from_cache' => true,
            'synced_at' => $cached['synced_at'] ?? null,
        ];
    }

    if ($action === 'get_products') {
        $search = strtolower(trim($params['search'] ?? ''));
        $category = $params['category'] ?? '';
        $sort_by = $params['sort_by'] ?? 'modal_desc';
        $limit = min((int)($params['limit'] ?? 500), 2000);

        $filtered = $products;
        if ($search !== '') {
            $filtered = array_values(array_filter($filtered, function($p) use ($search) {
                return str_contains(strtolower($p['namaitem'] ?? ''), $search)
                    || str_contains(strtolower($p['kodeitem'] ?? ''), $search);
            }));
        }
        if ($category !== '' && $category !== 'all') {
            $filtered = array_values(array_filter($filtered, function($p) use ($category) {
                return ($p['kategori'] ?? '') === $category;
            }));
        }

        usort($filtered, function($a, $b) use ($sort_by) {
            return match ($sort_by) {
                'stok_desc' => ($b['total_stok'] ?? 0) - ($a['total_stok'] ?? 0),
                'stok_asc' => ($a['total_stok'] ?? 0) - ($b['total_stok'] ?? 0),
                'jual_desc' => ($b['total_nilai_jual'] ?? 0) <=> ($a['total_nilai_jual'] ?? 0),
                'jual_asc' => ($a['total_nilai_jual'] ?? 0) <=> ($b['total_nilai_jual'] ?? 0),
                'modal_asc' => ($a['total_nilai_modal'] ?? 0) <=> ($b['total_nilai_modal'] ?? 0),
                'laba_desc' => ($b['potensi_laba'] ?? 0) <=> ($a['potensi_laba'] ?? 0),
                'laba_asc' => ($a['potensi_laba'] ?? 0) <=> ($b['potensi_laba'] ?? 0),
                'nama_asc' => strcmp($a['namaitem'] ?? '', $b['namaitem'] ?? ''),
                'nama_desc' => strcmp($b['namaitem'] ?? '', $a['namaitem'] ?? ''),
                default => ($b['total_nilai_modal'] ?? 0) <=> ($a['total_nilai_modal'] ?? 0),
            };
        });

        $filtered = array_slice($filtered, 0, $limit);
        $g_modal = array_sum(array_column($filtered, 'total_nilai_modal'));
        $g_jual = array_sum(array_column($filtered, 'total_nilai_jual'));

        return [
            'success' => true,
            'data' => $filtered,
            'grand_total_modal' => $g_modal,
            'grand_total_jual' => $g_jual,
            'total' => count($filtered),
            'from_cache' => true,
            'synced_at' => $cached['synced_at'] ?? null,
        ];
    }

    return ['success' => false, 'message' => 'Action tidak didukung dalam mode cache.'];
}

// ─────────────────────────────────────────────────────────────────────────
// GET PRODUCTS (DETAIL) — with cache fallback
// ─────────────────────────────────────────────────────────────────────────
if ($action === 'get_products') {
    if ($load_from_cache) {
        echo json_encode(getAsetFromCache($cached_data, 'get_products', [
            'search' => $_POST['search'] ?? '',
            'category' => $_POST['category'] ?? '',
            'sort_by' => $_POST['sort_by'] ?? 'modal_desc',
            'limit' => $_POST['limit'] ?? 500,
        ]));
        exit;
    }

    $search = trim($_POST['search'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sort_by = $_POST['sort_by'] ?? 'modal_desc';
    $limit = min((int)($_POST['limit'] ?? 500), 2000);

    $where = "1=1 AND LOWER(i.namaitem) NOT LIKE '%pesanan%' AND LOWER(i.namaitem) NOT LIKE '%jasa%'";
    $params = array();
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where .= " AND (LOWER(i.namaitem) LIKE LOWER($" . (count($params)+1) . ") OR LOWER(i.kodeitem) LIKE LOWER($" . (count($params)+1) . "))";
        $params[] = $like;
    }
    if ($category !== '' && $category !== 'all') {
        if ($category === '__NULL__') {
            $where .= " AND (TRIM(i.jenis) IS NULL OR TRIM(i.jenis) = '')";
        } else {
            $where .= " AND i.jenis = $" . (count($params)+1);
            $params[] = $category;
        }
    }

    $cek_hpp = @pg_query($db, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
    $punya_hpp = $cek_hpp && pg_fetch_result($cek_hpp, 0, 0) === 't';
    $hpp_expr = $punya_hpp ? "COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0)" : "0";
    $stok_sub = "(SELECT kodeitem, SUM(stok) AS total_stok FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0)";

    $order = match ($sort_by) {
        'stok_desc' => 's.total_stok DESC',
        'stok_asc' => 's.total_stok ASC',
        'jual_desc' => 'total_nilai_jual DESC',
        'jual_asc' => 'total_nilai_jual ASC',
        'modal_asc' => 'total_nilai_modal ASC',
        'laba_desc' => 'potensi_laba DESC',
        'laba_asc' => 'potensi_laba ASC',
        'nama_asc' => 'i.namaitem ASC',
        'nama_desc' => 'i.namaitem DESC',
        default => 'total_nilai_modal DESC',
    };

    $sql = "SELECT
        i.kodeitem,
        i.namaitem,
        COALESCE(NULLIF(TRIM(i.jenis), ''), 'Lainnya') AS kategori,
        s.total_stok,
        $hpp_expr AS hpp,
        i.hargajual1,
        (s.total_stok * $hpp_expr) AS total_nilai_modal,
        (s.total_stok * i.hargajual1) AS total_nilai_jual,
        (s.total_stok * (i.hargajual1 - $hpp_expr)) AS potensi_laba
    FROM tbl_item i
    INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
    WHERE $where
    ORDER BY $order
    LIMIT $limit";

    $r = @pg_query_params($db, $sql, $params);
    if (!$r) {
        echo json_encode(["success" => false, "message" => "Query gagal: " . pg_last_error($db)]);
        exit;
    }

    $products = [];
    $grand_modal = 0;
    $grand_jual = 0;
    while ($row = pg_fetch_assoc($r)) {
        $nm = (float)$row['total_nilai_modal'];
        $nj = (float)$row['total_nilai_jual'];
        $grand_modal += $nm;
        $grand_jual += $nj;
        $products[] = [
            'kodeitem' => $row['kodeitem'],
            'namaitem' => $row['namaitem'],
            'kategori' => $row['kategori'],
            'total_stok' => (int)$row['total_stok'],
            'hpp' => (float)$row['hpp'],
            'hargajual1' => (float)$row['hargajual1'],
            'total_nilai_modal' => $nm,
            'total_nilai_jual' => $nj,
            'potensi_laba' => (float)$row['potensi_laba'],
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $products,
        'grand_total_modal' => $grand_modal,
        'grand_total_jual' => $grand_jual,
        'total' => count($products),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────
// GET CATEGORIES — with cache fallback
// ─────────────────────────────────────────────────────────────────────────
if ($action === 'get_categories') {
    if ($load_from_cache) {
        echo json_encode(getAsetFromCache($cached_data, 'get_categories'));
        exit;
    }

    $cek_hpp = @pg_query($db, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
    $punya_hpp = $cek_hpp && pg_fetch_result($cek_hpp, 0, 0) === 't';
    $stok_sub = "(SELECT kodeitem, SUM(stok) AS total_stok FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0)";

    $r = @pg_query($db, "SELECT DISTINCT COALESCE(NULLIF(TRIM(jenis), ''), 'Lainnya') AS category FROM tbl_item i
        INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
        WHERE LOWER(i.namaitem) NOT LIKE '%pesanan%' AND LOWER(i.namaitem) NOT LIKE '%jasa%'
        ORDER BY category");
    $categories = [];
    while ($row = pg_fetch_assoc($r)) {
        $categories[] = $row['category'];
    }
    echo json_encode(['success' => true, 'data' => $categories]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────
// GET MUTASI ASET (date range) — this requires DB query, cache not suitable
// ─────────────────────────────────────────────────────────────────────────
if ($action === 'get_mutasi') {
    if ($load_from_cache) {
        echo json_encode(["success" => false, "message" => "Mutasi aset membutuhkan koneksi database langsung. Gunakan mode online."]);
        exit;
    }

    $tgl_mulai = $_POST['tgl_mulai'] ?? date('Y-m-d');
    $tgl_selesai = $_POST['tgl_selesai'] ?? date('Y-m-d');
    $mulai = pg_escape_string($db, $tgl_mulai);
    $selesai = pg_escape_string($db, $tgl_selesai);

    $cek_hpp = @pg_query($db, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
    $punya_hpp = $cek_hpp && pg_fetch_result($cek_hpp, 0, 0) === 't';

    // Cek tabel detail
    $cek_ikdt = @pg_query($db, "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_ikdt')");
    $has_ikdt = $cek_ikdt && pg_fetch_result($cek_ikdt, 0, 0) === 't';

    // 1. Total Pembelian (dari tbl_imhd)
    $total_pembelian = 0;
    $total_transaksi_beli = 0;
    $r_beli = @pg_query($db, "SELECT COUNT(*)::integer AS cnt, COALESCE(SUM(totalakhir), 0) AS total
        FROM tbl_imhd
        WHERE tanggal::date >= '$mulai'::date AND tanggal::date <= '$selesai'::date
        AND (notrsretur IS NULL) AND tipe != 'RKI'");
    if ($r_beli) {
        $row = pg_fetch_assoc($r_beli);
        $total_transaksi_beli = (int)$row['cnt'];
        $total_pembelian = (float)$row['total'];
    }

    // 2. Total Penjualan (dari tbl_ikhd)
    $total_penjualan = 0;
    $total_transaksi_jual = 0;
    $r_jual = @pg_query($db, "SELECT COUNT(*)::integer AS cnt, COALESCE(SUM(totalakhir), 0) AS total
        FROM tbl_ikhd
        WHERE tanggal::date >= '$mulai'::date AND tanggal::date <= '$selesai'::date
        AND notrsretur IS NULL");
    if ($r_jual) {
        $row = pg_fetch_assoc($r_jual);
        $total_transaksi_jual = (int)$row['cnt'];
        $total_penjualan = (float)$row['total'];
    }

    // 3. HPP Barang Terjual (dari tbl_ikdt)
    $total_hpp_terjual = 0;
    $total_item_terjual = 0;
    if ($has_ikdt && $punya_hpp) {
        $r_hpp = @pg_query($db, "SELECT
            COALESCE(SUM(COALESCE(d.jumlah, 0) * COALESCE(i.hpp, 0)), 0) AS total_hpp,
            COALESCE(SUM(COALESCE(d.jumlah, 0)), 0)::integer AS total_item
        FROM tbl_ikdt d
        JOIN tbl_ikhd h ON d.notransaksi = h.notransaksi
        LEFT JOIN (
            SELECT kodeitem, COALESCE(NULLIF(hargapokok, 0), NULLIF(tmphp, 0), 0) AS hpp FROM tbl_item
        ) i ON d.kodeitem = i.kodeitem
        WHERE h.tanggal::date >= '$mulai'::date AND h.tanggal::date <= '$selesai'::date
        AND h.notrsretur IS NULL");
        if ($r_hpp) {
            $row = pg_fetch_assoc($r_hpp);
            $total_hpp_terjual = (float)$row['total_hpp'];
            $total_item_terjual = (int)$row['total_item'];
        }
    }

    // 4. Mutasi Bersih
    $mutasi_bersih = $total_pembelian - $total_hpp_terjual;

    echo json_encode([
        'success' => true,
        'data' => [
            'tgl_mulai' => $tgl_mulai,
            'tgl_selesai' => $tgl_selesai,
            'total_pembelian' => $total_pembelian,
            'total_transaksi_beli' => $total_transaksi_beli,
            'total_penjualan' => $total_penjualan,
            'total_transaksi_jual' => $total_transaksi_jual,
            'total_hpp_terjual' => $total_hpp_terjual,
            'total_item_terjual' => $total_item_terjual,
            'mutasi_bersih' => $mutasi_bersih,
            'punya_hpp' => $punya_hpp && $has_ikdt,
        ]
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────
// SUMMARY (default) — with cache fallback
// ─────────────────────────────────────────────────────────────────────────
if ($load_from_cache) {
    echo json_encode(getAsetFromCache($cached_data, 'summary'));
    exit;
}

$cek_hpp = @pg_query($db, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
$punya_hpp = $cek_hpp && pg_fetch_result($cek_hpp, 0, 0) === 't';
$hpp_expr = $punya_hpp ? "COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0)" : "0";
$stok_sub = "(SELECT kodeitem, SUM(stok) AS total_stok FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0)";

$sql_summary = "SELECT
    COUNT(*)::integer AS total_items,
    COALESCE(SUM(s.total_stok), 0)::bigint AS total_stok,
    COALESCE(SUM(s.total_stok * $hpp_expr), 0) AS total_nilai_modal,
    COALESCE(SUM(s.total_stok * i.hargajual1), 0) AS total_nilai_jual,
    COALESCE(SUM(s.total_stok * (i.hargajual1 - $hpp_expr)), 0) AS total_potensi_laba
FROM tbl_item i
INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
WHERE LOWER(i.namaitem) NOT LIKE '%pesanan%'
  AND LOWER(i.namaitem) NOT LIKE '%jasa%'";

$r = @pg_query($db, $sql_summary);
if (!$r) {
    echo json_encode(["success" => false, "message" => "Query gagal: " . pg_last_error($db)]);
    exit;
}
$summary = pg_fetch_assoc($r);

// ─── BREAKDOWN PER KATEGORI ─────────────────────────────────────────────────
$sql_breakdown = "SELECT
    COALESCE(NULLIF(TRIM(i.jenis), ''), 'Lainnya') AS category,
    COUNT(*)::integer AS total_items,
    COALESCE(SUM(s.total_stok), 0)::bigint AS total_stok,
    COALESCE(SUM(s.total_stok * $hpp_expr), 0) AS total_nilai_modal,
    COALESCE(SUM(s.total_stok * i.hargajual1), 0) AS total_nilai_jual
FROM tbl_item i
INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
WHERE LOWER(i.namaitem) NOT LIKE '%pesanan%'
  AND LOWER(i.namaitem) NOT LIKE '%jasa%'
GROUP BY i.jenis
ORDER BY total_nilai_modal DESC";

$r2 = @pg_query($db, $sql_breakdown);
$breakdown = [];
while ($row = pg_fetch_assoc($r2)) {
    $breakdown[] = [
        'category' => $row['category'],
        'total_items' => (int)$row['total_items'],
        'total_stok' => (int)$row['total_stok'],
        'total_nilai_modal' => (float)$row['total_nilai_modal'],
        'total_nilai_jual' => (float)$row['total_nilai_jual'],
    ];
}

// ─── ITEMS WITH ZERO HPP ──────────────────────────────────────────────────
$items_tanpa_hpp = 0;
if ($punya_hpp) {
    $r3 = @pg_query($db, "SELECT COUNT(*)::integer AS cnt FROM tbl_item i
        INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
        WHERE COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0) = 0
          AND LOWER(i.namaitem) NOT LIKE '%pesanan%'
          AND LOWER(i.namaitem) NOT LIKE '%jasa%'");
    if ($r3) {
        $items_tanpa_hpp = (int)pg_fetch_result($r3, 0, 0);
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'total_items' => (int)$summary['total_items'],
        'total_stok' => (int)$summary['total_stok'],
        'total_nilai_modal' => (float)$summary['total_nilai_modal'],
        'total_nilai_jual' => (float)$summary['total_nilai_jual'],
        'total_potensi_laba' => (float)$summary['total_potensi_laba'],
        'punya_hpp' => $punya_hpp,
        'items_tanpa_hpp' => $items_tanpa_hpp,
        'breakdown' => $breakdown,
    ]
]);

} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
