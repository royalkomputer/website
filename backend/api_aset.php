<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

$db = getDB();
if (!$db) {
    echo json_encode(["success" => false, "message" => "Database tidak tersedia."]);
    exit;
}

$action = $_POST['action'] ?? 'summary';

$cek_hpp = @pg_query($db, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
$punya_hpp = $cek_hpp && pg_fetch_result($cek_hpp, 0, 0) === 't';

$hpp_expr = $punya_hpp
    ? "COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0)"
    : "0";

$stok_sub = "(SELECT kodeitem, SUM(stok) AS total_stok FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0)";

// ─────────────────────────────────────────────────────────
// GET PRODUCTS (DETAIL)
// ─────────────────────────────────────────────────────────
if ($action === 'get_products') {
    $search = trim($_POST['search'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sort_by = $_POST['sort_by'] ?? 'modal_desc';
    $limit = min((int)($_POST['limit'] ?? 500), 2000);

    $where = "1=1";
    if ($search !== '') {
        $s = pg_escape_string($db, $search);
        $where .= " AND (LOWER(i.namaitem) LIKE LOWER('%$s%') OR LOWER(i.kodeitem) LIKE LOWER('%$s%'))";
    }
    if ($category !== '' && $category !== 'all') {
        $cat = pg_escape_string($db, $category);
        if ($cat === '__NULL__') {
            $where .= " AND (TRIM(i.jenis) IS NULL OR TRIM(i.jenis) = '')";
        } else {
            $where .= " AND i.jenis = '$cat'";
        }
    }

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

    $r = @pg_query($db, $sql);
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

// ─────────────────────────────────────────────────────────
// GET CATEGORIES
// ─────────────────────────────────────────────────────────
if ($action === 'get_categories') {
    $r = @pg_query($db, "SELECT DISTINCT COALESCE(NULLIF(TRIM(jenis), ''), 'Lainnya') AS category FROM tbl_item i
        INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem ORDER BY category");
    $categories = [];
    while ($row = pg_fetch_assoc($r)) {
        $categories[] = $row['category'];
    }
    echo json_encode(['success' => true, 'data' => $categories]);
    exit;
}

// ─────────────────────────────────────────────────────────
// GET MUTASI ASET (date range)
// ─────────────────────────────────────────────────────────
if ($action === 'get_mutasi') {
    $tgl_mulai = $_POST['tgl_mulai'] ?? date('Y-m-d');
    $tgl_selesai = $_POST['tgl_selesai'] ?? date('Y-m-d');
    $mulai = pg_escape_string($db, $tgl_mulai);
    $selesai = pg_escape_string($db, $tgl_selesai);

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

// ─────────────────────────────────────────────────────────
// SUMMARY (default)
// ─────────────────────────────────────────────────────────
$sql_summary = "SELECT
    COUNT(*)::integer AS total_items,
    COALESCE(SUM(s.total_stok), 0)::bigint AS total_stok,
    COALESCE(SUM(s.total_stok * $hpp_expr), 0) AS total_nilai_modal,
    COALESCE(SUM(s.total_stok * i.hargajual1), 0) AS total_nilai_jual,
    COALESCE(SUM(s.total_stok * (i.hargajual1 - $hpp_expr)), 0) AS total_potensi_laba
FROM tbl_item i
INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem";

$r = @pg_query($db, $sql_summary);
if (!$r) {
    echo json_encode(["success" => false, "message" => "Query gagal: " . pg_last_error($db)]);
    exit;
}
$summary = pg_fetch_assoc($r);

// ─────────────────────────────────────────────────────────
// BREAKDOWN PER KATEGORI
// ─────────────────────────────────────────────────────────
$sql_breakdown = "SELECT
    COALESCE(NULLIF(TRIM(i.jenis), ''), 'Lainnya') AS category,
    COUNT(*)::integer AS total_items,
    COALESCE(SUM(s.total_stok), 0)::bigint AS total_stok,
    COALESCE(SUM(s.total_stok * $hpp_expr), 0) AS total_nilai_modal,
    COALESCE(SUM(s.total_stok * i.hargajual1), 0) AS total_nilai_jual
FROM tbl_item i
INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
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

// ─────────────────────────────────────────────────────────
// ITEMS WITH ZERO HPP
// ─────────────────────────────────────────────────────────
$items_tanpa_hpp = 0;
if ($punya_hpp) {
    $r3 = @pg_query($db, "SELECT COUNT(*)::integer AS cnt FROM tbl_item i
        INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
        WHERE COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0) = 0");
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
