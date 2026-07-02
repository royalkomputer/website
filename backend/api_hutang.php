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

$action = $_POST['action'] ?? 'get_list';
$jenis_nota = $_POST['jenis_nota'] ?? 'all';

function buildJenisWhere(string $jenis_nota): string {
    return match ($jenis_nota) {
        'BL'  => " AND i.tipe = 'BL'",
        'KI'  => " AND i.tipe = 'KI'",
        'RKI' => " AND i.tipe = 'RKI'",
        default => '',
    };
}

// ─────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────
if ($action === 'get_summary') {
    $base_where = "jmlkredit > 0 AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0 AND (notrsretur IS NULL)";

    $sql = "SELECT
        COUNT(*)::integer AS total_faktur,
        COALESCE(SUM(jmlkredit - COALESCE(krd_jml_byr, 0)), 0) AS total_hutang,
        COALESCE(SUM(CASE WHEN (byr_krd_jt IS NOT NULL AND byr_krd_jt < NOW() AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0) THEN (jmlkredit - COALESCE(krd_jml_byr, 0)) ELSE 0 END), 0) AS total_overdue,
        COALESCE(SUM(CASE WHEN byr_krd_jt IS NOT NULL AND byr_krd_jt < NOW() AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0 THEN 1 ELSE 0 END), 0)::integer AS overdue_count,
        COUNT(DISTINCT kodesupel)::integer AS total_supplier
    FROM tbl_imhd
    WHERE $base_where";

    $r = @pg_query($db, $sql);
    if (!$r) {
        echo json_encode(['success' => false, 'message' => 'Query gagal: ' . pg_last_error($db)]);
        exit;
    }
    $row = pg_fetch_assoc($r);

    // Count supplier
    $r2 = @pg_query($db, "SELECT COUNT(DISTINCT i.kodesupel)::integer AS total
        FROM tbl_imhd i
        WHERE i.jmlkredit > 0 AND (i.jmlkredit - COALESCE(i.krd_jml_byr, 0)) > 0 AND (i.notrsretur IS NULL)");
    $row2 = pg_fetch_assoc($r2);

    // Breakdown per jenis nota
    $r3 = @pg_query($db, "SELECT
        i.tipe,
        COUNT(*)::integer AS faktur,
        COALESCE(SUM(i.jmlkredit - COALESCE(i.krd_jml_byr, 0)), 0) AS total
    FROM tbl_imhd i
    WHERE i.jmlkredit > 0 AND (i.jmlkredit - COALESCE(i.krd_jml_byr, 0)) > 0 AND (i.notrsretur IS NULL)
    GROUP BY i.tipe
    ORDER BY i.tipe");

    $breakdown = [];
    while ($row3 = pg_fetch_assoc($r3)) {
        $tipe = $row3['tipe'];
        $label = match ($tipe) {
            'BL' => 'Pembelian',
            'KI' => 'Kongsi',
            'RKI' => 'Retur Kongsi',
            default => $tipe,
        };
        $breakdown[] = [
            'tipe' => $tipe,
            'label' => $label,
            'faktur' => (int)$row3['faktur'],
            'total' => (float)$row3['total'],
        ];
    }

    echo json_encode(['success' => true, 'data' => [
        'total_faktur' => (int)$row['total_faktur'],
        'total_hutang' => (float)$row['total_hutang'],
        'total_overdue' => (float)$row['total_overdue'],
        'overdue_count' => (int)$row['overdue_count'],
        'total_supplier' => (int)$row2['total'],
        'breakdown' => $breakdown,
    ]]);
    exit;
}

// ─────────────────────────────────────────────────────────
// GET LIST
// ─────────────────────────────────────────────────────────
$sort_by = $_POST['sort_by'] ?? 'due_date_asc';
$supplier_search = trim($_POST['supplier_search'] ?? '');
$overdue_only = $_POST['overdue_only'] ?? '';

$where = "i.jmlkredit > 0 AND (i.jmlkredit - COALESCE(i.krd_jml_byr, 0)) > 0 AND (i.notrsretur IS NULL)";
$where .= buildJenisWhere($jenis_nota);

if ($supplier_search !== '') {
    $ss = pg_escape_string($db, $supplier_search);
    $where .= " AND (LOWER(COALESCE(s.nama, '')) LIKE LOWER('%$ss%') OR LOWER(i.kodesupel) LIKE LOWER('%$ss%'))";
}

if ($overdue_only === '1') {
    $where .= " AND i.byr_krd_jt IS NOT NULL AND i.byr_krd_jt < NOW()";
}

$order = match ($sort_by) {
    'due_date_desc' => 'i.byr_krd_jt DESC NULLS LAST',
    'amount_desc' => 'sisa DESC',
    'amount_asc' => 'sisa ASC',
    'supplier_asc' => 's.nama ASC',
    'supplier_desc' => 's.nama DESC',
    default => 'i.byr_krd_jt ASC NULLS LAST',
};

$sql = "SELECT
    i.notransaksi,
    i.tanggal::date AS tgl_beli,
    i.kodesupel,
    COALESCE(s.nama, '-') AS nama_supplier,
    i.totalakhir,
    i.jmlkredit,
    COALESCE(i.krd_jml_byr, 0) AS krd_jml_byr,
    (i.jmlkredit - COALESCE(i.krd_jml_byr, 0)) AS sisa,
    i.byr_krd_jt,
    i.tipe,
    i.keterangan,
    i.notrsretur
FROM tbl_imhd i
LEFT JOIN tbl_supel s ON i.kodesupel = s.kode
WHERE $where
ORDER BY $order";

$r = @pg_query($db, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'message' => 'Query gagal: ' . pg_last_error($db)]);
    exit;
}

$rows = [];
$grand_total_faktur = 0;
$grand_total_sisa = 0;

while ($row = pg_fetch_assoc($r)) {
    $sisa = (float)$row['sisa'];
    $jt = $row['byr_krd_jt'];
    $now = date('Y-m-d H:i:s');

    $status = 'belum_jatuh_tempo';
    $hari_terlambat = 0;
    if ($jt && $sisa > 0) {
        $jt_ts = strtotime($jt);
        $now_ts = time();
        if ($jt_ts < $now_ts) {
            $status = 'terlambat';
            $hari_terlambat = floor(($now_ts - $jt_ts) / 86400);
        }
    }

    $tipe = $row['tipe'] ?? '';
    $jenis_label = match ($tipe) {
        'BL' => 'Pembelian',
        'KI' => 'Kongsi',
        'RKI' => 'Retur Kongsi',
        default => $tipe ?: '-',
    };

    $grand_total_faktur += (float)$row['totalakhir'];
    $grand_total_sisa += $sisa;

    $rows[] = [
        'notransaksi' => $row['notransaksi'],
        'tgl_beli' => $row['tgl_beli'],
        'kodesupel' => $row['kodesupel'],
        'nama_supplier' => $row['nama_supplier'],
        'totalakhir' => (float)$row['totalakhir'],
        'jmlkredit' => (float)$row['jmlkredit'],
        'krd_jml_byr' => (float)$row['krd_jml_byr'],
        'sisa' => $sisa,
        'byr_krd_jt' => $jt,
        'tipe' => $tipe,
        'jenis_label' => $jenis_label,
        'keterangan' => $row['keterangan'] ?? '',
        'status' => $status,
        'hari_terlambat' => $hari_terlambat,
    ];
}

echo json_encode([
    'success' => true,
    'data' => $rows,
    'grand_total_faktur' => $grand_total_faktur,
    'grand_total_sisa' => $grand_total_sisa,
    'total' => count($rows),
]);
