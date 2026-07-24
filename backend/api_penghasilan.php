<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cors.php';
handleCORS();
require_once __DIR__ . '/config.php';
requireLogin();

error_reporting(E_ERROR | E_PARSE);

$db = getDB();

if ($db) {
    $range = $_GET['range'] ?? 'month';
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';

    if ($start && $end) {
        $date_filter = "h.tanggal >= '$start' AND h.tanggal <= '$end 23:59:59'";
        $label = date('j M Y', strtotime($start)) . ' s/d ' . date('j M Y', strtotime($end));
    } elseif ($range === 'day') {
        $date_filter = "h.tanggal >= CURRENT_DATE";
        $label = date('j M Y');
    } elseif ($range === 'week') {
        $date_filter = "h.tanggal >= CURRENT_DATE - INTERVAL '7 days'";
        $label = date('j M Y', strtotime('-7 days')) . ' s/d ' . date('j M Y');
    } else {
        $year = date('Y');
        $month = date('m');
        $bulan_start = sprintf('%s-%02d-29', $year, $month - 1);
        $bulan_end = sprintf('%s-%02d-28', $year, $month);
        $date_filter = "h.tanggal >= '$bulan_start' AND h.tanggal < '$bulan_end' + INTERVAL '1 day'";
        $label = date('j M Y', strtotime($bulan_start)) . ' s/d ' . date('j M Y', strtotime($bulan_end));
    }

    $sql = "SELECT COUNT(DISTINCT h.notransaksi) AS total_transaksi,
                   COALESCE(SUM(h.totalakhir), 0) AS total_penjualan,
                   COALESCE(SUM(d.jumlah), 0) AS total_item,
                   COALESCE(SUM(d.jumlah * i.hargapokok), 0) AS total_hpp
            FROM tbl_ikhd h
            JOIN tbl_ikdt d ON h.notransaksi = d.notransaksi
            LEFT JOIN tbl_item i ON d.kodeitem = i.kodeitem
            WHERE $date_filter";
    $result = @pg_query($db, $sql);
    $summary = pg_fetch_assoc($result);
    if (!$summary) $summary = ['total_transaksi' => 0, 'total_penjualan' => 0, 'total_item' => 0, 'total_hpp' => 0];

    $total_trans = (int)$summary['total_transaksi'];
    $total_jual = (float)$summary['total_penjualan'];
    $total_hpp = (float)$summary['total_hpp'];

    $sql2 = "SELECT h.notransaksi AS no_faktur, h.tanggal AS tgl, h.totalakhir AS total,
                    d.kodeitem, COALESCE(i.namaitem, d.kodeitem) AS namaitem, d.jumlah AS qty, d.harga
             FROM tbl_ikhd h
             JOIN tbl_ikdt d ON h.notransaksi = d.notransaksi
             LEFT JOIN tbl_item i ON d.kodeitem = i.kodeitem
             WHERE $date_filter
             ORDER BY h.tanggal DESC, h.notransaksi ASC";
    $result2 = @pg_query($db, $sql2);

    $merged = [];
    if ($result2) {
        while ($row = pg_fetch_assoc($result2)) {
            $key = $row['no_faktur'];
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'no_faktur' => $row['no_faktur'],
                    'tanggal' => $row['tgl'],
                    'total' => (float)$row['total'],
                    'items' => [],
                ];
            }
            $merged[$key]['items'][] = [
                'kode' => $row['kodeitem'],
                'nama' => $row['namaitem'],
                'qty' => (float)$row['qty'],
                'harga' => (float)$row['harga'],
            ];
        }
    }

    echo json_encode([
        'summary' => [
            'total_transaksi' => $total_trans,
            'total_penjualan' => $total_jual,
            'total_item' => (int)$summary['total_item'],
            'total_hpp' => $total_hpp,
            'penghasilan_bersih' => $total_jual - $total_hpp,
            'rata_rata' => $total_trans > 0 ? round($total_jual / $total_trans) : 0,
            'label' => $label,
        ],
        'transactions' => array_values($merged),
    ]);
    exit;
}

$cache = @file_get_contents(__DIR__ . '/data/cache_penghasilan.json');
if ($cache) {
    echo $cache;
} else {
    echo json_encode(['summary' => ['total_transaksi' => 0, 'total_penjualan' => 0, 'total_item' => 0, 'total_hpp' => 0, 'penghasilan_bersih' => 0, 'rata_rata' => 0, 'label' => ''], 'transactions' => []]);
}