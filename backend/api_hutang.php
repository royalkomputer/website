<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

try {

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
    exit;
}

$cache_file = __DIR__ . '/data/cache_hutang.json';
$cached_data = null;

// Try to load from cache first
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
        echo json_encode(["success" => false, "message" => "Database tidak tersedia dan cache hutang kosong."]);
        exit;
    }
}

$action = $_POST['action'] ?? 'get_list';

// ─── CACHE HELPERS ─────────────────────────────────────────────────────
function getHutangFromCache(array $cached, string $action, array $params = []): array {
    $data = $cached['data'] ?? [];
    $list = $cached['list'] ?? [];
    $grand_faktur = $cached['grand_total_faktur'] ?? 0;
    $grand_sisa = $cached['grand_total_sisa'] ?? 0;

    if ($action === 'get_summary') {
        return [
            'success' => true,
            'data' => $data,
            'from_cache' => true,
            'synced_at' => $cached['synced_at'] ?? null,
        ];
    }

    if ($action === 'get_list') {
        $sort_by = $params['sort_by'] ?? 'due_date_asc';
        $supplier_search = strtolower(trim($params['supplier_search'] ?? ''));
        $jenis_nota = $params['jenis_nota'] ?? 'all';
        $overdue_only = ($params['overdue_only'] ?? '') === '1';

        $filtered = $list;
        if ($supplier_search !== '') {
            $filtered = array_values(array_filter($filtered, function($r) use ($supplier_search) {
                return str_contains(strtolower($r['nama_supplier'] ?? ''), $supplier_search)
                    || str_contains(strtolower($r['kodesupel'] ?? ''), $supplier_search);
            }));
        }
        if ($jenis_nota !== 'all') {
            $filtered = array_values(array_filter($filtered, function($r) use ($jenis_nota) {
                return ($r['tipe'] ?? '') === $jenis_nota;
            }));
        }
        if ($overdue_only) {
            $filtered = array_values(array_filter($filtered, function($r) {
                return $r['status'] === 'terlambat';
            }));
        }

        usort($filtered, function($a, $b) use ($sort_by) {
            return match ($sort_by) {
                'due_date_desc' => strcmp($b['byr_krd_jt'] ?? '', $a['byr_krd_jt'] ?? ''),
                'amount_desc' => ($b['sisa'] ?? 0) <=> ($a['sisa'] ?? 0),
                'amount_asc' => ($a['sisa'] ?? 0) <=> ($b['sisa'] ?? 0),
                'supplier_asc' => strcmp($a['nama_supplier'] ?? '', $b['nama_supplier'] ?? ''),
                'supplier_desc' => strcmp($b['nama_supplier'] ?? '', $a['nama_supplier'] ?? ''),
                default => strcmp($a['byr_krd_jt'] ?? '', $b['byr_krd_jt'] ?? ''),
            };
        });

        $gt_faktur = array_sum(array_column($filtered, 'totalakhir'));
        $gt_sisa = array_sum(array_column($filtered, 'sisa'));

        return [
            'success' => true,
            'data' => $filtered,
            'grand_total_faktur' => $gt_faktur,
            'grand_total_sisa' => $gt_sisa,
            'total' => count($filtered),
            'from_cache' => true,
            'synced_at' => $cached['synced_at'] ?? null,
        ];
    }

    return ['success' => false, 'message' => 'Action tidak didukung dalam mode cache.'];
}

// ─────────────────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────────────────
if ($action === 'get_summary') {
    if ($load_from_cache) {
        echo json_encode(getHutangFromCache($cached_data, 'get_summary'));
        exit;
    }

    $base_where = "jmlkredit > 0 AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0 AND (notrsretur IS NULL) AND tipe != 'RKI'";

    $sql = "SELECT
        COUNT(*)::integer AS total_faktur,
        COALESCE(SUM(jmlkredit - COALESCE(krd_jml_byr, 0)), 0) AS total_hutang,
        COALESCE(SUM(CASE WHEN (byr_krd_jt IS NOT NULL AND byr_krd_jt < NOW() AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0) THEN (jmlkredit - COALESCE(krd_jml_byr, 0)) ELSE 0 END), 0) AS total_overdue,
        COALESCE(SUM(CASE WHEN byr_krd_jt IS NOT NULL AND byr_krd_jt < NOW() AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0 THEN 1 ELSE 0 END), 0)::integer AS overdue_count
    FROM tbl_imhd
    WHERE $base_where";

    $r = @pg_query($db, $sql);
    if (!$r) {
        echo json_encode(['success' => false, 'message' => 'Query gagal: ' . pg_last_error($db)]);
        exit;
    }
    $row = pg_fetch_assoc($r);

    // Count supplier
    $r2 = @pg_query($db, "SELECT COUNT(DISTINCT kodesupel)::integer AS total
        FROM tbl_imhd WHERE $base_where");
    $total_supplier = $r2 ? (int)pg_fetch_result($r2, 0, 0) : 0;

    // Breakdown per jenis nota
    $r3 = @pg_query($db, "SELECT
        i.tipe,
        COUNT(*)::integer AS faktur,
        COALESCE(SUM(i.jmlkredit - COALESCE(i.krd_jml_byr, 0)), 0) AS total
    FROM tbl_imhd i
    WHERE $base_where
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
        'total_supplier' => $total_supplier,
        'breakdown' => $breakdown,
    ]]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────
// GET LIST — with cache fallback
// ─────────────────────────────────────────────────────────────────────────
$sort_by = $_POST['sort_by'] ?? 'due_date_asc';
$supplier_search = trim($_POST['supplier_search'] ?? '');
$jenis_nota = $_POST['jenis_nota'] ?? 'all';
$overdue_only = $_POST['overdue_only'] ?? '';

if ($load_from_cache) {
    echo json_encode(getHutangFromCache($cached_data, 'get_list', [
        'sort_by' => $sort_by,
        'supplier_search' => $supplier_search,
        'jenis_nota' => $jenis_nota,
        'overdue_only' => $overdue_only,
    ]));
    exit;
}

function buildJenisWhere(string $jenis_nota): string {
    return match ($jenis_nota) {
        'BL'  => " AND i.tipe = 'BL'",
        'KI'  => " AND i.tipe = 'KI'",
        'RKI' => " AND i.tipe = 'RKI'",
        default => '',
    };
}

$where = "i.jmlkredit > 0 AND (i.jmlkredit - COALESCE(i.krd_jml_byr, 0)) > 0 AND (i.notrsretur IS NULL) AND i.tipe != 'RKI'";
$where .= buildJenisWhere($jenis_nota);

$params = array();

if ($supplier_search !== '') {
    $like = '%' . $supplier_search . '%';
    $where .= " AND (LOWER(COALESCE(s.nama, '')) LIKE LOWER($" . (count($params)+1) . ") OR LOWER(i.kodesupel) LIKE LOWER($" . (count($params)+1) . "))";
    $params[] = $like;
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

} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
