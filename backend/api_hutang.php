<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cors.php';
handleCORS();
require_once __DIR__ . '/config.php';
requireLogin();

error_reporting(E_ERROR | E_PARSE);

$db = getDB();

if ($db) {
    $sql = "SELECT h.no_faktur, h.tgl, h.total_faktur, h.jmlkredit AS total_sisa,
                   COALESCE(h.keterangan, '') AS keterangan, h.tgl_jatuh_tempo,
                   COALESCE(s.nama, 'Unknown') AS supplier
            FROM tbl_imhd h
            LEFT JOIN tbl_supel s ON h.kodesupel = s.kode
            WHERE h.jmlkredit > 0
              AND (h.jenis IS NULL OR h.jenis NOT IN ('RKI'))
            ORDER BY h.tgl DESC";
    $result = @pg_query($db, $sql);
    if ($result) {
        $data = [];
        $grand_faktur = 0;
        $grand_sisa = 0;
        $now = time();
        while ($row = pg_fetch_assoc($result)) {
            $total_faktur = (float)$row['total_faktur'];
            $total_sisa = (float)$row['total_sisa'];
            $grand_faktur += $total_faktur;
            $grand_sisa += $total_sisa;

            $jatuh_tempo = $row['tgl_jatuh_tempo'] ? strtotime($row['tgl_jatuh_tempo']) : 0;
            $hari_terlambat = 0;
            if ($jatuh_tempo && $total_sisa > 0) {
                $hari_terlambat = max(0, (int)(($now - $jatuh_tempo) / 86400));
            }

            $data[] = [
                'no_faktur' => $row['no_faktur'],
                'supplier' => $row['supplier'],
                'tanggal' => $row['tgl'],
                'total_faktur' => $total_faktur,
                'total_sisa' => $total_sisa,
                'keterangan' => $row['keterangan'] ?? '',
                'status' => $total_sisa <= 0 ? 'lunas' : ($hari_terlambat > 0 ? 'terlambat' : 'aktif'),
                'hari_terlambat' => $hari_terlambat,
            ];
        }

        echo json_encode([
            'data' => $data,
            'grand_total_faktur' => $grand_faktur,
            'grand_total_sisa' => $grand_sisa,
            'total' => count($data),
        ]);
        exit;
    }
}

$cache = @file_get_contents(__DIR__ . '/data/cache_hutang.json');
if ($cache) {
    echo $cache;
} else {
    echo json_encode(['data' => [], 'grand_total_faktur' => 0, 'grand_total_sisa' => 0, 'total' => 0]);
}