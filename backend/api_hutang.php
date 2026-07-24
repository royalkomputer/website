<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cors.php';
handleCORS();
require_once __DIR__ . '/config.php';
requireLogin();

error_reporting(E_ERROR | E_PARSE);

$db = getDB();

if ($db) {
    $tipe_filter = $_GET['tipe'] ?? '';
    $sort = $_GET['sort'] ?? 'tgl_desc';

    $where = "(h.jmlkredit - COALESCE(h.krd_jml_byr, 0)) > 0 AND (h.tipe IS NULL OR h.tipe NOT IN ('RKI'))";
    if ($tipe_filter === 'BL') $where .= " AND (h.tipe IS NULL OR h.tipe = 'BL')";
    elseif ($tipe_filter === 'KI') $where .= " AND h.tipe = 'KI'";

    $order = "h.tanggal DESC";
    if ($sort === 'jatuh_tempo_asc') $order = "h.byr_krd_jt ASC NULLS LAST, h.tanggal DESC";

    $sql = "SELECT h.notransaksi AS no_faktur, h.tanggal, h.totalakhir AS total_faktur,
                   (h.jmlkredit - COALESCE(h.krd_jml_byr, 0)) AS total_sisa,
                   COALESCE(h.keterangan, '') AS keterangan, h.byr_krd_jt AS tgl_jatuh_tempo,
                   COALESCE(s.nama, 'Unknown') AS supplier,
                   COALESCE(h.tipe, 'BL') AS tipe
            FROM tbl_imhd h
            LEFT JOIN tbl_supel s ON h.kodesupel = s.kode
            WHERE $where
            ORDER BY $order";
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
                'tanggal' => $row['tanggal'],
                'total_faktur' => $total_faktur,
                'total_sisa' => $total_sisa,
                'keterangan' => $row['keterangan'] ?? '',
                'tipe' => $row['tipe'] ?? 'BL',
                'tgl_jatuh_tempo' => $row['tgl_jatuh_tempo'] ?? '',
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