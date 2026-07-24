<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cors.php';
handleCORS();
require_once __DIR__ . '/config.php';
requireLogin();

error_reporting(E_ERROR | E_PARSE);

$db = getDB();

if ($db) {
    $sql = "SELECT COUNT(DISTINCT h.no_faktur) AS total_transaksi,
                   COALESCE(SUM(h.total), 0) AS total_penjualan,
                   COALESCE(SUM(d.qty), 0) AS total_item
            FROM tbl_ikhd h
            JOIN tbl_ikdt d ON h.no_faktur = d.no_faktur
            WHERE h.tgl >= date_trunc('month', CURRENT_DATE)";
    $result = @pg_query($db, $sql);
    $summary = pg_fetch_assoc($result);
    if (!$summary) $summary = ['total_transaksi' => 0, 'total_penjualan' => 0, 'total_item' => 0];

    $total_trans = (int)$summary['total_transaksi'];
    $total_jual = (float)$summary['total_penjualan'];

    $sql2 = "SELECT h.no_faktur, h.tgl, h.total, d.kodeitem, COALESCE(i.namaitem, d.kodeitem) AS namaitem, d.qty, d.harga
             FROM tbl_ikhd h
             JOIN tbl_ikdt d ON h.no_faktur = d.no_faktur
             LEFT JOIN tbl_item i ON d.kodeitem = i.kodeitem
             WHERE h.tgl >= date_trunc('month', CURRENT_DATE)
             ORDER BY h.tgl DESC, h.no_faktur ASC";
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
            'rata_rata' => $total_trans > 0 ? round($total_jual / $total_trans) : 0,
            'bulan' => date('F Y'),
        ],
        'transactions' => array_values($merged),
    ]);
    exit;
}

$cache = @file_get_contents(__DIR__ . '/data/cache_penghasilan.json');
if ($cache) {
    echo $cache;
} else {
    echo json_encode(['summary' => ['total_transaksi' => 0, 'total_penjualan' => 0, 'total_item' => 0, 'rata_rata' => 0, 'bulan' => date('F Y')], 'transactions' => []]);
}