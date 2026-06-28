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

// ─────────────────────────────────────────────────────────
// GET AVAILABLE DATE RANGE
// ─────────────────────────────────────────────────────────
if ($action === 'get_date_range') {
    // Cari tanggal transaksi pertama dan terakhir di database
    $r = @pg_query($db, "SELECT
        MIN(tanggal)::date AS min_date,
        MAX(tanggal)::date AS max_date
    FROM tbl_ikhd");
    if (!$r) {
        echo json_encode(['success' => false, 'message' => 'Gagal membaca data tanggal.']);
        exit;
    }
    $row = pg_fetch_assoc($r);
    echo json_encode(['success' => true, 'data' => [
        'min_date' => $row['min_date'],
        'max_date' => $row['max_date']
    ]]);
    exit;
}

// ─────────────────────────────────────────────────────────
// SUMMARY (default)
// ─────────────────────────────────────────────────────────
$tgl_mulai = $_POST['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_POST['tgl_selesai'] ?? date('Y-m-d');

$tgl_mulai_esc = pg_escape_string($db, $tgl_mulai);
$tgl_selesai_esc = pg_escape_string($db, $tgl_selesai);

// 1. Ringkasan penjualan
$sql_summary = "SELECT
    COUNT(*)::integer AS total_transaksi,
    COALESCE(SUM(COALESCE(totalakhir, 0)), 0) AS total_penjualan,
    COALESCE(AVG(COALESCE(totalakhir, 0)), 0) AS rata_rata_per_transaksi
FROM tbl_ikhd
WHERE tanggal::date >= '$tgl_mulai_esc'::date
  AND tanggal::date <= '$tgl_selesai_esc'::date
  AND notrsretur IS NULL";

$r_summary = @pg_query($db, $sql_summary);
if (!$r_summary) {
    echo json_encode(['success' => false, 'message' => 'Query gagal: ' . pg_last_error($db)]);
    exit;
}
$summary = pg_fetch_assoc($r_summary);

// 2. Perhitungan hari dalam rentang
$hari_rentang = max(1, (strtotime($tgl_selesai) - strtotime($tgl_mulai)) / 86400 + 1);
$rata_harian = $summary['total_penjualan'] > 0 ? round($summary['total_penjualan'] / $hari_rentang, 2) : 0;

// 3. Transaksi per hari (untuk grafik / detail harian)
$sql_harian = "SELECT
    tanggal::date AS tgl,
    COUNT(*)::integer AS jumlah,
    COALESCE(SUM(COALESCE(totalakhir, 0)), 0) AS total
FROM tbl_ikhd
WHERE tanggal::date >= '$tgl_mulai_esc'::date
  AND tanggal::date <= '$tgl_selesai_esc'::date
  AND notrsretur IS NULL
GROUP BY tanggal::date
ORDER BY tanggal::date";

$r_harian = @pg_query($db, $sql_harian);
$harian = [];
if ($r_harian) {
    while ($row = pg_fetch_assoc($r_harian)) {
        $harian[] = [
            'tgl' => $row['tgl'],
            'jumlah' => (int)$row['jumlah'],
            'total' => (float)$row['total']
        ];
    }
}

// 4. Hitung total item terjual dari tbl_ikdt (detail penjualan)
$total_item_terjual = 0;
$check_detail = @pg_query($db, "SELECT EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_name = 'tbl_ikdt'
)");
$has_detail_table = $check_detail && pg_fetch_result($check_detail, 0, 0) === 't';

if ($has_detail_table) {
    $sql_items = "SELECT COALESCE(SUM(COALESCE(d.jumlah, 0)), 0)::integer AS total_item
    FROM tbl_ikdt d
    JOIN tbl_ikhd h ON d.notransaksi = h.notransaksi
    WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
      AND h.tanggal::date <= '$tgl_selesai_esc'::date
      AND h.notrsretur IS NULL";

    $r_items = @pg_query($db, $sql_items);
    if ($r_items) {
        $row_items = pg_fetch_assoc($r_items);
        $total_item_terjual = (int)($row_items['total_item'] ?? 0);
    }
}

// 5. Transaksi terakhir (10 terbaru)
$sql_terbaru = "SELECT
    h.notransaksi,
    h.tanggal::date AS tgl,
    h.totalakhir,
    sp.nama AS pelanggan,
    sp.kode AS kode_pelanggan
FROM tbl_ikhd h
LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode
WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
  AND h.tanggal::date <= '$tgl_selesai_esc'::date
  AND h.notrsretur IS NULL
ORDER BY h.tanggal DESC
LIMIT 50";

$r_terbaru = @pg_query($db, $sql_terbaru);
$transaksi_terbaru = [];
if ($r_terbaru) {
    while ($row = pg_fetch_assoc($r_terbaru)) {
        $transaksi_terbaru[] = [
            'notransaksi' => $row['notransaksi'],
            'tgl' => $row['tgl'],
            'totalakhir' => (float)$row['totalakhir'],
            'pelanggan' => $row['pelanggan'] ?? '-',
            'kode_pelanggan' => $row['kode_pelanggan'] ?? '-'
        ];
    }
}

// Response
echo json_encode([
    'success' => true,
    'data' => [
        'tgl_mulai' => $tgl_mulai,
        'tgl_selesai' => $tgl_selesai,
        'total_transaksi' => (int)$summary['total_transaksi'],
        'total_penjualan' => (float)$summary['total_penjualan'],
        'rata_rata_per_transaksi' => round((float)$summary['rata_rata_per_transaksi'], 2),
        'rata_rata_per_hari' => $rata_harian,
        'hari_rentang' => (int)$hari_rentang,
        'harian' => $harian,
        'total_item_terjual' => $total_item_terjual,
        'transaksi_terbaru' => $transaksi_terbaru
    ]
]);
