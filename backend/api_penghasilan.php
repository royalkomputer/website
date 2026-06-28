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

// Daftar kategori deduksi: [key => [nama_lengkap, ...]]
// Key = identifier used in response, values = customer names (case-insensitive match)
$deductions = [
    'bonus' => ['BONUS'],
    'rusak' => ['RUSAK']
];

// Build WHERE conditions for each deduction
$deduction_wheres = [];
$all_deduction_names = [];
foreach ($deductions as $key => $names) {
    $lower_names = array_map('strtolower', $names);
    $escaped = array_map(function($n) use ($db) { return pg_escape_string($db, $n); }, $lower_names);
    $deduction_wheres[$key] = "LOWER(COALESCE(sp.nama, '')) IN ('" . implode("','", $escaped) . "')";
    $all_deduction_names = array_merge($all_deduction_names, $lower_names);
}
// Condition to exclude ALL deduction customers
$all_deduction_where = "LOWER(COALESCE(sp.nama, '')) IN ('" . implode("','", $all_deduction_names) . "')";

// Helper function to run deduction queries
function getDeductionSummary($db, $where, $tgl_mulai_esc, $tgl_selesai_esc) {
    $sql = "SELECT
        COUNT(*)::integer AS total_transaksi,
        COALESCE(SUM(COALESCE(h.totalakhir, 0)), 0) AS total_penjualan
    FROM tbl_ikhd h
    LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode
    WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
      AND h.tanggal::date <= '$tgl_selesai_esc'::date
      AND h.notrsretur IS NULL
      AND $where";
    $r = @pg_query($db, $sql);
    if (!$r) return ['total_transaksi' => 0, 'total_penjualan' => 0];
    $row = pg_fetch_assoc($r);
    return [
        'total_transaksi' => (int)$row['total_transaksi'],
        'total_penjualan' => (float)$row['total_penjualan']
    ];
}

// Cek apakah tabel detail (tbl_ikdt) dan tbl_item tersedia
$check_detail = @pg_query($db, "SELECT EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_ikdt'
)");
$has_detail_table = $check_detail && pg_fetch_result($check_detail, 0, 0) === 't';

$check_hpp = @pg_query($db, "SELECT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'tbl_item' AND column_name = 'hargapokok'
)");
$has_hpp_column = $check_hpp && pg_fetch_result($check_hpp, 0, 0) === 't';
$can_calc_profit = $has_detail_table && $has_hpp_column;

// 1. Ringkasan penjualan (total)
$sql_summary_total = "SELECT
    COUNT(*)::integer AS total_transaksi,
    COALESCE(SUM(COALESCE(h.totalakhir, 0)), 0) AS total_penjualan,
    COALESCE(AVG(COALESCE(h.totalakhir, 0)), 0) AS rata_rata_per_transaksi
FROM tbl_ikhd h
LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode
WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
  AND h.tanggal::date <= '$tgl_selesai_esc'::date
  AND h.notrsretur IS NULL";

$r_summary_total = @pg_query($db, $sql_summary_total);
if (!$r_summary_total) {
    echo json_encode(['success' => false, 'message' => 'Query gagal: ' . pg_last_error($db)]);
    exit;
}
$summary_total = pg_fetch_assoc($r_summary_total);
$total_penjualan = (float)$summary_total['total_penjualan'];

// 2. Ringkasan per kategori deduksi (BONUS, RUSAK, ...)
$deduction_data = [];
$total_deductions_penjualan = 0;
$total_deductions_hpp = 0;

foreach ($deductions as $key => $names) {
    $where = $deduction_wheres[$key];
    $d = getDeductionSummary($db, $where, $tgl_mulai_esc, $tgl_selesai_esc);
    $deduction_data[$key] = $d;
    $total_deductions_penjualan += $d['total_penjualan'];
    $deduction_data[$key]['total_hpp'] = 0;
    $deduction_data[$key]['total_item'] = 0;
}

// 3. Perhitungan hari dalam rentang
$hari_rentang = max(1, (strtotime($tgl_selesai) - strtotime($tgl_mulai)) / 86400 + 1);
$rata_harian = $total_penjualan > 0 ? round($total_penjualan / $hari_rentang, 2) : 0;

// 4. Hitung HPP & Pendapatan Bersih
$total_hpp = 0;
$total_item_terjual = 0;

if ($can_calc_profit) {
    $sql_hpp = "SELECT
        COALESCE(SUM(COALESCE(d.jumlah, 0) * COALESCE(i.hpp, 0)), 0) AS total_hpp,
        COALESCE(SUM(COALESCE(d.jumlah, 0)), 0)::integer AS total_item
    FROM tbl_ikdt d
    JOIN tbl_ikhd h ON d.notransaksi = h.notransaksi
    LEFT JOIN (
        SELECT kodeitem,
            COALESCE(NULLIF(hargapokok, 0), NULLIF(tmphp, 0), 0) AS hpp
        FROM tbl_item
    ) i ON d.kodeitem = i.kodeitem
    WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
      AND h.tanggal::date <= '$tgl_selesai_esc'::date
      AND h.notrsretur IS NULL";

    $r_hpp = @pg_query($db, $sql_hpp);
    if ($r_hpp) {
        $row_hpp = pg_fetch_assoc($r_hpp);
        $total_hpp = (float)($row_hpp['total_hpp'] ?? 0);
        $total_item_terjual = (int)($row_hpp['total_item'] ?? 0);
    }

    // HPP per kategori deduksi
    foreach ($deductions as $key => $names) {
        $where = $deduction_wheres[$key];
        $sql_ded_hpp = "SELECT
            COALESCE(SUM(COALESCE(d.jumlah, 0) * COALESCE(i.hpp, 0)), 0) AS total_hpp,
            COALESCE(SUM(COALESCE(d.jumlah, 0)), 0)::integer AS total_item
        FROM tbl_ikdt d
        JOIN tbl_ikhd h ON d.notransaksi = h.notransaksi
        LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode
        LEFT JOIN (
            SELECT kodeitem,
                COALESCE(NULLIF(hargapokok, 0), NULLIF(tmphp, 0), 0) AS hpp
            FROM tbl_item
        ) i ON d.kodeitem = i.kodeitem
        WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
          AND h.tanggal::date <= '$tgl_selesai_esc'::date
          AND h.notrsretur IS NULL
          AND $where";

        $r_ded_hpp = @pg_query($db, $sql_ded_hpp);
        if ($r_ded_hpp) {
            $row_ded_hpp = pg_fetch_assoc($r_ded_hpp);
            $deduction_data[$key]['total_hpp'] = (float)($row_ded_hpp['total_hpp'] ?? 0);
            $deduction_data[$key]['total_item'] = (int)($row_ded_hpp['total_item'] ?? 0);
        }
        $total_deductions_hpp += $deduction_data[$key]['total_hpp'];
    }
} elseif ($has_detail_table) {
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

$pendapatan_bersih = $total_penjualan - $total_hpp;
$margin_persen = $total_penjualan > 0 ? round(($pendapatan_bersih / $total_penjualan) * 100, 1) : 0;

// Pendapatan bersih setelah dikurangi modal BONUS & RUSAK
// Rumus: pendapatan_bersih dikurangi dengan HPP/Harga Pokok dari barang BONUS dan RUSAK
$pendapatan_bersih_non_ded = $pendapatan_bersih - $total_deductions_hpp;
$margin_non_ded = $total_penjualan > 0 ? round(($pendapatan_bersih_non_ded / $total_penjualan) * 100, 1) : 0;

// 5. Transaksi per hari (dengan HPP & deduksi harian)
// Note: Use CASE WHEN for count instead of FILTER(WHERE...) for broader PostgreSQL compatibility
$sql_harian = "SELECT
    h.tanggal::date AS tgl,
    COUNT(*)::integer AS jumlah,
    COALESCE(SUM(COALESCE(h.totalakhir, 0)), 0) AS total";

// Add deduction columns per category using CASE WHEN for both sum and count
foreach ($deductions as $key => $names) {
    $where = $deduction_wheres[$key];
    $sql_harian .= ",
    COALESCE(SUM(CASE WHEN $where THEN COALESCE(h.totalakhir, 0) ELSE 0 END), 0) AS {$key}_total,
    COALESCE(SUM(CASE WHEN $where THEN 1 ELSE 0 END), 0)::integer AS {$key}_jumlah";
}

if ($can_calc_profit) {
    $sql_harian .= ",
    COALESCE(SUM(sub.hpp_harian), 0) AS total_hpp";
    foreach ($deductions as $key => $names) {
        $where = $deduction_wheres[$key];
        $sql_harian .= ",
    COALESCE(SUM(CASE WHEN $where THEN sub.hpp_harian ELSE 0 END), 0) AS {$key}_hpp";
    }
}

$sql_harian .= "
FROM tbl_ikhd h
LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode";

if ($can_calc_profit) {
    $sql_harian .= "
LEFT JOIN (
    SELECT d.notransaksi,
        SUM(COALESCE(d.jumlah, 0) * COALESCE(i.hpp, 0)) AS hpp_harian
    FROM tbl_ikdt d
    LEFT JOIN (
        SELECT kodeitem,
            COALESCE(NULLIF(hargapokok, 0), NULLIF(tmphp, 0), 0) AS hpp
        FROM tbl_item
    ) i ON d.kodeitem = i.kodeitem
    GROUP BY d.notransaksi
) sub ON h.notransaksi = sub.notransaksi";
}

$sql_harian .= "
WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
  AND h.tanggal::date <= '$tgl_selesai_esc'::date
  AND h.notrsretur IS NULL
GROUP BY h.tanggal::date
ORDER BY h.tanggal::date";

$r_harian = @pg_query($db, $sql_harian);
$harian = [];
if ($r_harian) {
    while ($row = pg_fetch_assoc($r_harian)) {
        $harian_item = [
            'tgl' => $row['tgl'],
            'jumlah' => (int)$row['jumlah'],
            'total' => (float)$row['total']
        ];
        // Per-kategori deduction daily data
        foreach ($deductions as $key => $names) {
            $harian_item["{$key}_jumlah"] = (int)$row["{$key}_jumlah"];
            $harian_item["{$key}_total"] = (float)$row["{$key}_total"];
        }
        if ($can_calc_profit) {
            $hpp_harian = (float)($row['total_hpp'] ?? 0);
            $harian_item['total_hpp'] = $hpp_harian;
            $harian_item['pendapatan_bersih'] = (float)$row['total'] - $hpp_harian;
            $ded_hpp_harian = 0;
            foreach ($deductions as $key => $names) {
                $val = (float)($row["{$key}_hpp"] ?? 0);
                $harian_item["{$key}_hpp"] = $val;
                $ded_hpp_harian += $val;
            }
            $harian_item['pendapatan_bersih_ded'] = $harian_item['pendapatan_bersih'] - $ded_hpp_harian;
            $harian_item['margin_ded'] = (float)$row['total'] > 0 ? round(($harian_item['pendapatan_bersih_ded'] / (float)$row['total']) * 100, 1) : 0;
        }
        $harian[] = $harian_item;
    }
}

// 6. Transaksi terakhir (50 terbaru)
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
$response = [
    'success' => true,
    'data' => [
        'tgl_mulai' => $tgl_mulai,
        'tgl_selesai' => $tgl_selesai,
        'total_transaksi' => (int)$summary_total['total_transaksi'],
        'total_penjualan' => $total_penjualan,
        'rata_rata_per_transaksi' => round((float)$summary_total['rata_rata_per_transaksi'], 2),
        'rata_rata_per_hari' => $rata_harian,
        'hari_rentang' => (int)$hari_rentang,
        'harian' => $harian,
        'total_item_terjual' => $total_item_terjual,
        'transaksi_terbaru' => $transaksi_terbaru,
        'total_hpp' => $total_hpp,
        'pendapatan_bersih' => $pendapatan_bersih,
        'margin_persen' => $margin_persen,
        'can_calc_profit' => $can_calc_profit,
        // Deductions (BONUS, RUSAK, etc.)
        'deductions' => $deduction_data,
        'total_deductions_penjualan' => $total_deductions_penjualan,
        'total_deductions_hpp' => $total_deductions_hpp,
        'pendapatan_bersih_non_ded' => $pendapatan_bersih_non_ded,
        'margin_non_ded' => $margin_non_ded
    ]
];

echo json_encode($response);
