<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cors.php';
handleCORS();
require_once __DIR__ . '/config.php';
requireLogin();

error_reporting(E_ERROR | E_PARSE);

$db = getDB();

if ($db) {
    $sql = "SELECT i.kodeitem, i.namaitem, i.jenis, i.hargapokok, i.hargajual1, COALESCE(SUM(s.stok), 0) AS stok
            FROM tbl_item i
            JOIN tbl_itemstok s ON i.kodeitem = s.kodeitem
            GROUP BY i.kodeitem, i.namaitem, i.jenis, i.hargapokok, i.hargajual1
            HAVING SUM(s.stok) > 0
            ORDER BY i.namaitem ASC";
    $result = @pg_query($db, $sql);
    if ($result) {
        $data = [];
        while ($row = pg_fetch_assoc($result)) {
            $harga_pokok = (float)($row['hargapokok'] ?? 0);
            $harga_jual = (float)($row['hargajual1'] ?? 0);
            $stok = (float)$row['stok'];
            $data[] = [
                'kode' => $row['kodeitem'],
                'nama' => $row['namaitem'],
                'kategori' => empty(trim($row['jenis'] ?? '')) ? 'Lainnya' : trim($row['jenis']),
                'stok' => $stok,
                'harga_pokok' => $harga_pokok,
                'harga_jual' => $harga_jual,
                'total_modal' => round($harga_pokok * $stok),
                'total_nilai_jual' => round($harga_jual * $stok),
            ];
        }
        echo json_encode($data);
        exit;
    }
}

$cache = @file_get_contents(__DIR__ . '/data/cache_aset.json');
if ($cache) {
    echo $cache;
} else {
    echo json_encode([]);
}