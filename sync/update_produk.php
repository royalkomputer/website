<?php
/**
 * sync/update_produk.php — IPOS4 Auto Sync Agent
 *
 * Menghubungkan ke IPOS4 (PostgreSQL), mengambil produk dengan stok > 0,
 * menghasilkan cache_produk.json, dan menyinkronkan ke frontend/ dan backend/data/.
 *
 * Fitur:
 *   - Auto-retry koneksi database (3x dengan exponential backoff)
 *   - Jika IPOS4 tidak bisa dihubungi → fallback ke cache terakhir (tidak exit error)
 *   --watch mode: jalankan terus-menerus dengan interval tertentu
 *   - Sinkronisasi foto dari backend/uploads/ ke frontend/uploads/
 *
 * Usage:
 *   php update_produk.php                            # Jalan sekali
 *   php update_produk.php --watch                    # Jalan terus (default interval 300s)
 *   php update_produk.php --watch --interval=60      # Setiap 60 detik
 *   php update_produk.php --once                     # Jalan sekali (default)
 *   php update_produk.php --watch --git-push         # Watch + auto commit & push
 *   php update_produk.php --watch --git-interval=1800 # Git push setiap 30 menit
 *
 * Log: sync/sync.log
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once __DIR__ . '/config.php';

// ─── CLI ARGS ───────────────────────────────────────────────────────────────
$watch_mode = false;
$watch_interval = 300; // default 5 menit
$git_push = false;
$git_interval = 3600; // default 1 jam

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i] ?? '';
    if ($arg === '--watch') {
        $watch_mode = true;
    } elseif (str_starts_with($arg, '--interval=')) {
        $watch_interval = (int) substr($arg, 11);
        if ($watch_interval < 10) $watch_interval = 10;
    } elseif ($arg === '--once') {
        $watch_mode = false;
    } elseif ($arg === '--git-push') {
        $git_push = true;
    } elseif (str_starts_with($arg, '--git-interval=')) {
        $git_interval = (int) substr($arg, 15);
        if ($git_interval < 60) $git_interval = 60;
    }
}

$log_file = __DIR__ . '/sync.log';

function write_log(string $message): void {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " $message\n", FILE_APPEND);
}

function log_and_echo(string $message): void {
    write_log($message);
    echo "  $message\n";
}

function format_bytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ─── RETRY KONEKSI ──────────────────────────────────────────────────────────
function connectWithRetry(int $max_retries = 3, int $base_delay = 2): false|PgSql\Connection {
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        if ($attempt > 1) {
            $delay = $base_delay * pow(2, $attempt - 2); // 2, 4, 8 detik
            log_and_echo("Retry #$attempt — menunggu {$delay}s...");
            sleep($delay);
        }
        $conn = getDBConnection();
        if ($conn !== false) {
            if ($attempt > 1) log_and_echo("Koneksi berhasil pada percobaan ke-$attempt");
            return $conn;
        }
    }
    return false;
}

// ─── FUNGSI SYNC UTAMA ──────────────────────────────────────────────────────
function runSync(): array {
    $result = [
        'success' => true,
        'products' => 0,
        'db_connected' => false,
        'photos_synced' => 0,
        'cache_written' => false,
        'duration' => 0,
        'error' => null,
    ];
    $start_time = microtime(true);

    echo "─── Photo Sync ───────────────────────────────\n";
    log_and_echo("Memeriksa backend/uploads/ untuk foto baru...");
    $backend_uploads = __DIR__ . '/../backend/uploads/';
    $frontend_uploads = __DIR__ . '/../frontend/uploads/';

    $synced = 0;
    if (!is_dir($backend_uploads)) {
        log_and_echo("WARNING — backend/uploads/ tidak ditemukan: " . $backend_uploads);
    } else {
        if (!is_dir($frontend_uploads)) {
            mkdir($frontend_uploads, 0777, true);
            log_and_echo("Membuat direktori frontend/uploads/");
        }

        $all_photos = [];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($backend_uploads . '*.' . $ext);
            if ($matches) $all_photos = array_merge($all_photos, $matches);
        }
        $photo_count = count($all_photos);
        log_and_echo("Ditemukan $photo_count file gambar di backend/uploads/");

        foreach ($all_photos as $photo) {
            $filename = basename($photo);
            $dest = $frontend_uploads . $filename;
            $backend_mtime = filemtime($photo);

            if (!file_exists($dest) || $backend_mtime > filemtime($dest)) {
                $copied = copy($photo, $dest);
                if ($copied) {
                    $synced++;
                    write_log("[PHOTO SYNC] Disalin: $filename");
                } else {
                    write_log("[PHOTO SYNC] GAGAL menyalin: $filename");
                }
            }
        }
        log_and_echo("Foto: $synced disalin, " . ($photo_count - $synced) . " sudah terbaru");

        // ─── CLEANUP: Hapus file non-WEBP dari frontend/uploads jika ada versi WEBP ───
        $cleaned = 0;
        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $non_webp = glob($frontend_uploads . '*.' . $ext);
            foreach ($non_webp as $file) {
                $base = pathinfo($file, PATHINFO_FILENAME);
                // Cek apakah ada versi .webp dengan nama dasar yang sama
                if (file_exists($frontend_uploads . $base . '.webp')) {
                    unlink($file);
                    $cleaned++;
                    write_log("[PHOTO CLEANUP] Hapus: " . basename($file) . " (sudah ada versi WEBP)");
                }
            }
        }
        if ($cleaned > 0) {
            log_and_echo("Bersihkan: $cleaned file non-WEBP dihapus (sudah ada versi WEBP)");
        }
    }

    $result['photos_synced'] = $synced;

    // ─── KONEKSI DATABASE ──────────────────────────────────────────────────
    echo "─── Database ─────────────────────────────────\n";
    log_and_echo("Menghubungkan ke IPOS4: host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME);
    $conn = connectWithRetry(3, 2);

    if (!$conn) {
        $result['db_connected'] = false;
        $err = error_get_last();
        $err_msg = $err ? $err['message'] : 'Unknown error';
        log_and_echo("GAGAL — Tidak bisa terhubung ke IPOS4 setelah 3 percobaan: $err_msg");
        log_and_echo("Menggunakan cache_produk.json yang ada sebagai fallback...");

        // Coba load cache yang sudah ada untuk ditulis ulang
        $cache_path = __DIR__ . '/cache_produk.json';
        if (file_exists($cache_path)) {
            $cached = file_get_contents($cache_path);
            $produk = json_decode($cached, true) ?: [];
            $result['products'] = count($produk);
            log_and_echo("Fallback: " . count($produk) . " produk dari cache terakhir");
            // Tetap tulis ulang cache (misal untuk update foto)
            $result['cache_written'] = writeCacheFiles($produk);
            $result['success'] = false;
        } else {
            log_and_echo("Tidak ada cache fallback. Menulis array kosong.");
            writeCacheFiles([]);
            $result['cache_written'] = true;
        }

        $result['error'] = $err_msg;
        $result['duration'] = round(microtime(true) - $start_time, 4);
        return $result;
    }

    $result['db_connected'] = true;
    log_and_echo("Terhubung ke IPOS4!");

    // ─── QUERY ──────────────────────────────────────────────────────────────
    $sql = "SELECT i.kodeitem AS id, i.namaitem AS name, i.jenis AS category,
                i.hargajual1 AS price,
                COALESCE(s.total_stok, 0) AS stock,
                COALESCE(w.deskripsi, '') AS description
            FROM tbl_item i
            INNER JOIN (
                SELECT kodeitem, SUM(stok) as total_stok
                FROM tbl_itemstok
                GROUP BY kodeitem
                HAVING SUM(stok) > 0
            ) s ON i.kodeitem = s.kodeitem
            LEFT JOIN tbl_web_deskripsi w ON i.kodeitem = w.kodeitem
        WHERE LOWER(i.namaitem) NOT LIKE '%pesanan%'
          AND LOWER(i.namaitem) NOT LIKE '%jasa%'";

    log_and_echo("Menjalankan query produk...");
    $query_start = microtime(true);
    $pg_result = @pg_query($conn, $sql);
    $query_duration = round(microtime(true) - $query_start, 4);

    if (!$pg_result) {
        $err = pg_last_error($conn);
        log_and_echo("GAGAL — Query error setelah {$query_duration}s: $err");
        pg_close($conn);
        $result['success'] = false;
        $result['error'] = $err;
        $result['duration'] = round(microtime(true) - $start_time, 4);
        return $result;
    }

    $rows = pg_num_rows($pg_result);
    log_and_echo("Query mengembalikan $rows baris dalam {$query_duration}s");

    // ─── PROCESS ────────────────────────────────────────────────────────────
    echo "─── Processing ───────────────────────────────\n";
    $produk = [];
    $no_image_count = 0;

    while ($row = pg_fetch_assoc($pg_result)) {
        $row['price'] = (float) $row['price'];
        $row['stock'] = (float) $row['stock'];
        if (empty(trim($row['category']))) {
            $row['category'] = 'Lainnya';
        }

        $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $row['id']);
        $uploads_path = __DIR__ . '/../frontend/uploads/';
        $images = [];

        // Prioritaskan WEBP, fallback ke format lain jika tidak ada WEBP
        $matched_files = glob($uploads_path . $safe_kode . '_*.webp');
        if (empty($matched_files)) {
            foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
                $matches = glob($uploads_path . $safe_kode . '_*.' . $ext);
                if ($matches) $matched_files = array_merge($matched_files, $matches);
            }
        }
        sort($matched_files);
        // Legacy file: cek .webp dulu, baru format lain
        $legacy_extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif'];
        foreach ($legacy_extensions as $ext) {
            $legacy_file = $uploads_path . $safe_kode . '.' . $ext;
            if (file_exists($legacy_file)) {
                array_unshift($matched_files, $legacy_file);
                break;
            }
        }

        if (!empty($matched_files)) {
            foreach ($matched_files as $file) {
                $images[] = 'uploads/' . basename($file) . '?v=' . filemtime($file);
            }
            $row['image'] = $images[0];
            $row['images'] = $images;
        } else {
            $no_image_count++;
            $default_img = "https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500";
            $row['image'] = $default_img;
            $row['images'] = [$default_img];
        }

        $produk[] = $row;
    }

    $result['products'] = count($produk);
    log_and_echo("Diproses " . count($produk) . " produk (" . $no_image_count . " tanpa foto)");

    // ─── WRITE CACHE ────────────────────────────────────────────────────────
    $result['cache_written'] = writeCacheFiles($produk);

    // ─── SYNC ADMIN DATA (aset, hutang, penghasilan) ────────────────────
    echo "─── Admin Data Sync ────────────────────────────\n";
    log_and_echo("Menyinkronkan data aset, hutang, dan penghasilan...");

    $aset_result = runAsetSync($conn);
    $hutang_result = runHutangSync($conn);
    $penghasilan_result = runPenghasilanSync($conn);

    if (!$aset_result['success']) {
        log_and_echo("WARNING — Sync aset gagal: " . ($aset_result['error'] ?? 'unknown error'));
    }
    if (!$hutang_result['success']) {
        log_and_echo("WARNING — Sync hutang gagal: " . ($hutang_result['error'] ?? 'unknown error'));
    }
    if (!$penghasilan_result['success']) {
        log_and_echo("WARNING — Sync penghasilan gagal: " . ($penghasilan_result['error'] ?? 'unknown error'));
    }

    // Close connection after all queries are done
    pg_close($conn);

    $result['duration'] = round(microtime(true) - $start_time, 4);

    return $result;
}

// ─── GIT AUTO PUSH ─────────────────────────────────────────────────────────
function gitCommitAndPush(): bool {
    $repo_dir = __DIR__ . '/..';
    $output = [];
    $exit_code = 0;

    // Stage semua perubahan
    exec("cd /d \"$repo_dir\" && git add -A 2>&1", $output, $exit_code);
    if ($exit_code !== 0) {
        log_and_echo("GIT: Gagal git add: " . implode("\n", $output));
        return false;
    }

    // Cek apakah ada perubahan
    exec("cd /d \"$repo_dir\" && git diff --cached --quiet 2>&1", $output, $exit_code);
    if ($exit_code === 0) {
        log_and_echo("GIT: Tidak ada perubahan, skip commit");
        return true;
    }

    // Commit
    $msg = "sync: product data update " . date('Y-m-d H:i:s');
    exec("cd /d \"$repo_dir\" && git commit -m \"$msg\" 2>&1", $output, $exit_code);
    if ($exit_code !== 0) {
        log_and_echo("GIT: Gagal commit: " . implode("\n", $output));
        return false;
    }
    log_and_echo("GIT: Commit berhasil");

    // Push
    exec("cd /d \"$repo_dir\" && git push origin main 2>&1", $output, $exit_code);
    if ($exit_code !== 0) {
        log_and_echo("GIT: Gagal push: " . implode("\n", $output));
        return false;
    }
    log_and_echo("GIT: Push berhasil");
    return true;
}

function writeCacheFiles(array $produk): bool {
    echo "─── Cache Files ──────────────────────────────\n";
    $json_output = json_encode($produk);

    if ($json_output === false) {
        log_and_echo("GAGAL — JSON encoding error: " . json_last_error_msg());
        return false;
    }

    $targets = [
        __DIR__ . '/cache_produk.json'             => 'sync/',
        __DIR__ . '/../frontend/cache_produk.json'  => 'frontend/',
        __DIR__ . '/../backend/data/cache_produk.json' => 'backend/data/',
    ];

    $all_ok = true;
    foreach ($targets as $path => $label) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $written = file_put_contents($path, $json_output);
        if ($written === false) {
            log_and_echo("GAGAL — Tidak bisa menulis ke $label");
            $all_ok = false;
        } else {
            log_and_echo("Ditulis ke $label: " . format_bytes($written));
        }
    }

    return $all_ok;
}

// ─── WRITE DATA CACHE FILES (for admin menus: aset, hutang, penghasilan) ───
function writeDataCacheFile(string $filename, array $data, string $label): bool {
    $json_output = json_encode($data);
    if ($json_output === false) {
        log_and_echo("GAGAL — JSON encoding error untuk $label: " . json_last_error_msg());
        return false;
    }

    $targets = [
        __DIR__ . '/' . $filename               => 'sync/',
        __DIR__ . '/../backend/data/' . $filename => 'backend/data/',
    ];

    $all_ok = true;
    foreach ($targets as $path => $dir_label) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $written = file_put_contents($path, $json_output);
        if ($written === false) {
            log_and_echo("GAGAL — Tidak bisa menulis $label ke $dir_label");
            $all_ok = false;
        } else {
            log_and_echo("Ditulis $label ke $dir_label: " . format_bytes($written));
        }
    }

    return $all_ok;
}

// ─── SYNC ASET DATA ────────────────────────────────────────────────────────
function runAsetSync($conn): array {
    log_and_echo("Memulai sync data aset...");
    $result = [
        'success' => true,
        'total_items' => 0,
        'has_hpp' => false,
        'error' => null,
    ];

    // Check HPP column
    $cek_hpp = @pg_query($conn, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
    $punya_hpp = $cek_hpp && pg_fetch_result($cek_hpp, 0, 0) === 't';
    $hpp_expr = $punya_hpp
        ? "COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0)"
        : "0";
    $stok_sub = "(SELECT kodeitem, SUM(stok) AS total_stok FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0)";
    $exclude = "LOWER(i.namaitem) NOT LIKE '%pesanan%' AND LOWER(i.namaitem) NOT LIKE '%jasa%'";

    // Summary
    $sql_sum = "SELECT
        COUNT(*)::integer AS total_items,
        COALESCE(SUM(s.total_stok), 0)::bigint AS total_stok,
        COALESCE(SUM(s.total_stok * $hpp_expr), 0) AS total_nilai_modal,
        COALESCE(SUM(s.total_stok * i.hargajual1), 0) AS total_nilai_jual,
        COALESCE(SUM(s.total_stok * (i.hargajual1 - $hpp_expr)), 0) AS total_potensi_laba
    FROM tbl_item i
    INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
    WHERE $exclude";

    $r = @pg_query($conn, $sql_sum);
    if (!$r) {
        $result['success'] = false;
        $result['error'] = pg_last_error($conn);
        log_and_echo("GAGAL query summary aset: " . $result['error']);
        return $result;
    }
    $summary = pg_fetch_assoc($r);

    // Breakdown per category
    $sql_brk = "SELECT
        COALESCE(NULLIF(TRIM(i.jenis), ''), 'Lainnya') AS category,
        COUNT(*)::integer AS total_items,
        COALESCE(SUM(s.total_stok), 0)::bigint AS total_stok,
        COALESCE(SUM(s.total_stok * $hpp_expr), 0) AS total_nilai_modal,
        COALESCE(SUM(s.total_stok * i.hargajual1), 0) AS total_nilai_jual
    FROM tbl_item i
    INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
    WHERE $exclude
    GROUP BY i.jenis
    ORDER BY total_nilai_modal DESC";

    $r2 = @pg_query($conn, $sql_brk);
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

    // Categories
    $sql_cat = "SELECT DISTINCT COALESCE(NULLIF(TRIM(jenis), ''), 'Lainnya') AS category FROM tbl_item i
        INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
        WHERE $exclude ORDER BY category";
    $r3 = @pg_query($conn, $sql_cat);
    $categories = [];
    while ($row = pg_fetch_assoc($r3)) {
        $categories[] = $row['category'];
    }

    // Items without HPP
    $items_tanpa_hpp = 0;
    if ($punya_hpp) {
        $r4 = @pg_query($conn, "SELECT COUNT(*)::integer AS cnt FROM tbl_item i
            INNER JOIN $stok_sub s ON i.kodeitem = s.kodeitem
            WHERE COALESCE(NULLIF(i.hargapokok, 0), NULLIF(i.tmphp, 0), 0) = 0
              AND $exclude");
        if ($r4) {
            $items_tanpa_hpp = (int)pg_fetch_result($r4, 0, 0);
        }
    }

    // All products (for detail view)
    $sql_prod = "SELECT
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
    WHERE $exclude
    ORDER BY i.namaitem ASC";

    $r5 = @pg_query($conn, $sql_prod);
    $products = [];
    $grand_modal = 0;
    $grand_jual = 0;
    while ($row = pg_fetch_assoc($r5)) {
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

    $data = [
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
            'categories' => $categories,
        ],
        'grand_total_modal' => $grand_modal,
        'grand_total_jual' => $grand_jual,
        'products' => $products,
        'total' => count($products),
        'synced_at' => date('Y-m-d H:i:s'),
    ];

    $written = writeDataCacheFile('cache_aset.json', $data, 'aset');
    $result['total_items'] = (int)$summary['total_items'];
    $result['has_hpp'] = $punya_hpp;
    log_and_echo("Sync aset selesai: " . $result['total_items'] . " item" . ($punya_hpp ? '' : ' (tanpa HPP)'));
    return $result;
}

// ─── SYNC HUTANG DATA ──────────────────────────────────────────────────────
function runHutangSync($conn): array {
    log_and_echo("Memulai sync data hutang...");
    $result = [
        'success' => true,
        'total_faktur' => 0,
        'total_hutang' => 0,
        'error' => null,
    ];

    // Summary (tbl_imhd without alias - no ambiguity since no JOIN)
    $base_where = "jmlkredit > 0 AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0 AND (notrsretur IS NULL) AND tipe != 'RKI'";
    $sql_sum = "SELECT
        COUNT(*)::integer AS total_faktur,
        COALESCE(SUM(jmlkredit - COALESCE(krd_jml_byr, 0)), 0) AS total_hutang,
        COALESCE(SUM(CASE WHEN (byr_krd_jt IS NOT NULL AND byr_krd_jt < NOW() AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0) THEN (jmlkredit - COALESCE(krd_jml_byr, 0)) ELSE 0 END), 0) AS total_overdue,
        COALESCE(SUM(CASE WHEN byr_krd_jt IS NOT NULL AND byr_krd_jt < NOW() AND (jmlkredit - COALESCE(krd_jml_byr, 0)) > 0 THEN 1 ELSE 0 END), 0)::integer AS overdue_count
    FROM tbl_imhd
    WHERE $base_where";

    $r = @pg_query($conn, $sql_sum);
    if (!$r) {
        $result['success'] = false;
        $result['error'] = pg_last_error($conn);
        log_and_echo("GAGAL query summary hutang: " . $result['error']);
        return $result;
    }
    $row = pg_fetch_assoc($r);

    // Total suppliers
    $r_sup = @pg_query($conn, "SELECT COUNT(DISTINCT kodesupel)::integer AS total
        FROM tbl_imhd WHERE $base_where");
    $total_supplier = $r_sup ? (int)pg_fetch_result($r_sup, 0, 0) : 0;

    // Prefixed base_where for queries with JOINs (to avoid ambiguous column references)
    $base_where_prefixed = "i.jmlkredit > 0 AND (i.jmlkredit - COALESCE(i.krd_jml_byr, 0)) > 0 AND (i.notrsretur IS NULL) AND i.tipe != 'RKI'";

    // Breakdown per jenis nota
    $r_brk = @pg_query($conn, "SELECT
        i.tipe,
        COUNT(*)::integer AS faktur,
        COALESCE(SUM(i.jmlkredit - COALESCE(i.krd_jml_byr, 0)), 0) AS total
    FROM tbl_imhd i
    WHERE $base_where
    GROUP BY i.tipe ORDER BY i.tipe");
    $breakdown = [];
    if ($r_brk) {
        while ($row_brk = pg_fetch_assoc($r_brk)) {
            $tipe = $row_brk['tipe'];
            $label = match ($tipe) {
                'BL' => 'Pembelian',
                'KI' => 'Kongsi',
                'RKI' => 'Retur Kongsi',
                default => $tipe ?: '-',
            };
            $breakdown[] = [
                'tipe' => $tipe,
                'label' => $label,
                'faktur' => (int)$row_brk['faktur'],
                'total' => (float)$row_brk['total'],
            ];
        }
    } else {
        log_and_echo("WARNING — Query breakdown hutang gagal: " . pg_last_error($conn));
    }

    // All outstanding debts (uses $base_where_prefixed because of JOIN with tbl_supel s)
    $sql_list = "SELECT
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
    WHERE $base_where_prefixed
    ORDER BY i.byr_krd_jt ASC NULLS LAST";

    $r_list = @pg_query($conn, $sql_list);
    $rows = [];
    $grand_total_faktur = 0;
    $grand_total_sisa = 0;
    $now_ts = time();

    if (!$r_list) {
        $result['success'] = false;
        $result['error'] = pg_last_error($conn);
        log_and_echo("GAGAL query list hutang: " . $result['error']);
        return $result;
    }

    while ($row_list = pg_fetch_assoc($r_list)) {
        $sisa = (float)$row_list['sisa'];
        $jt = $row_list['byr_krd_jt'];
        $status = 'belum_jatuh_tempo';
        $hari_terlambat = 0;
        if ($jt && $sisa > 0) {
            $jt_ts = strtotime($jt);
            if ($jt_ts < $now_ts) {
                $status = 'terlambat';
                $hari_terlambat = floor(($now_ts - $jt_ts) / 86400);
            }
        }
        $tipe = $row_list['tipe'] ?? '';
        $jenis_label = match ($tipe) {
            'BL' => 'Pembelian',
            'KI' => 'Kongsi',
            'RKI' => 'Retur Kongsi',
            default => $tipe ?: '-',
        };
        $grand_total_faktur += (float)$row_list['totalakhir'];
        $grand_total_sisa += $sisa;

        $rows[] = [
            'notransaksi' => $row_list['notransaksi'],
            'tgl_beli' => $row_list['tgl_beli'],
            'kodesupel' => $row_list['kodesupel'],
            'nama_supplier' => $row_list['nama_supplier'],
            'totalakhir' => (float)$row_list['totalakhir'],
            'jmlkredit' => (float)$row_list['jmlkredit'],
            'krd_jml_byr' => (float)$row_list['krd_jml_byr'],
            'sisa' => $sisa,
            'byr_krd_jt' => $jt,
            'tipe' => $tipe,
            'jenis_label' => $jenis_label,
            'keterangan' => $row_list['keterangan'] ?? '',
            'status' => $status,
            'hari_terlambat' => $hari_terlambat,
        ];
    }

    $data = [
        'success' => true,
        'data' => [
            'total_faktur' => (int)$row['total_faktur'],
            'total_hutang' => (float)$row['total_hutang'],
            'total_overdue' => (float)$row['total_overdue'],
            'overdue_count' => (int)$row['overdue_count'],
            'total_supplier' => $total_supplier,
            'breakdown' => $breakdown,
        ],
        'list' => $rows,
        'grand_total_faktur' => $grand_total_faktur,
        'grand_total_sisa' => $grand_total_sisa,
        'total' => count($rows),
        'synced_at' => date('Y-m-d H:i:s'),
    ];

    $written = writeDataCacheFile('cache_hutang.json', $data, 'hutang');
    $result['total_faktur'] = (int)$row['total_faktur'];
    $result['total_hutang'] = (float)$row['total_hutang'];
    log_and_echo("Sync hutang selesai: " . $result['total_faktur'] . " faktur, total Rp " . number_format($result['total_hutang'], 0, ',', '.'));
    return $result;
}

// ─── SYNC PENGHASILAN DATA ─────────────────────────────────────────────────
function runPenghasilanSync($conn): array {
    log_and_echo("Memulai sync data penghasilan...");
    $result = [
        'success' => true,
        'total_transaksi' => 0,
        'total_penjualan' => 0,
        'error' => null,
    ];

    // Get available date range
    $r_range = @pg_query($conn, "SELECT MIN(tanggal)::date AS min_date, MAX(tanggal)::date AS max_date FROM tbl_ikhd");
    $min_date = null;
    $max_date = null;
    if ($r_range) {
        $row_range = pg_fetch_assoc($r_range);
        $min_date = $row_range['min_date'];
        $max_date = $row_range['max_date'];
    }

    // Current month summary (default view)
    $tgl_mulai = date('Y-m-01');
    $tgl_selesai = date('Y-m-d');
    $tgl_mulai_esc = pg_escape_string($conn, $tgl_mulai);
    $tgl_selesai_esc = pg_escape_string($conn, $tgl_selesai);

    // Check detail table & HPP column
    $check_detail = @pg_query($conn, "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tbl_ikdt')");
    $has_detail = $check_detail && pg_fetch_result($check_detail, 0, 0) === 't';
    $check_hpp = @pg_query($conn, "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tbl_item' AND column_name = 'hargapokok')");
    $has_hpp = $check_hpp && pg_fetch_result($check_hpp, 0, 0) === 't';
    $can_profit = $has_detail && $has_hpp;

    // Exclude pesanan
    $exclude_pesanan = '';
    if ($has_detail) {
        $pesanan_ids = "SELECT d2.notransaksi FROM tbl_ikdt d2 JOIN tbl_item i2 ON d2.kodeitem = i2.kodeitem WHERE LOWER(i2.namaitem) LIKE '%pesanan%'";
        $exclude_pesanan = "AND h.notransaksi NOT IN ($pesanan_ids)";
    }

    // Full month summary
    $sql_sum = "SELECT
        COUNT(*)::integer AS total_transaksi,
        COALESCE(SUM(COALESCE(h.totalakhir, 0)), 0) AS total_penjualan,
        COALESCE(AVG(COALESCE(h.totalakhir, 0)), 0) AS rata_rata
    FROM tbl_ikhd h
    LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode
    WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
      AND h.tanggal::date <= '$tgl_selesai_esc'::date
      AND h.notrsretur IS NULL
      $exclude_pesanan";

    $r_sum = @pg_query($conn, $sql_sum);
    if (!$r_sum) {
        $result['error'] = pg_last_error($conn);
        $result['success'] = false;
        log_and_echo("GAGAL query summary penghasilan: " . $result['error']);
        return $result;
    }
    $summary = pg_fetch_assoc($r_sum);

    $total_penjualan = (float)$summary['total_penjualan'];
    $total_transaksi = (int)$summary['total_transaksi'];
    $rata_rata = (float)$summary['rata_rata'];

    // Calculate HPP
    $total_hpp = 0;
    $total_item_terjual = 0;
    $pendapatan_bersih = 0;
    $margin_persen = 0;

    if ($can_profit) {
        $sql_hpp = "SELECT
            COALESCE(SUM(COALESCE(d.jumlah, 0) * COALESCE(i.hpp, 0)), 0) AS total_hpp,
            COALESCE(SUM(COALESCE(d.jumlah, 0)), 0)::integer AS total_item
        FROM tbl_ikdt d
        JOIN tbl_ikhd h ON d.notransaksi = h.notransaksi
        LEFT JOIN (
            SELECT kodeitem, COALESCE(NULLIF(hargapokok, 0), NULLIF(tmphp, 0), 0) AS hpp FROM tbl_item
        ) i ON d.kodeitem = i.kodeitem
        WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
          AND h.tanggal::date <= '$tgl_selesai_esc'::date
          AND h.notrsretur IS NULL";
        if ($exclude_pesanan) {
            $sql_hpp .= " AND h.notransaksi NOT IN ($pesanan_ids)";
        }

        $r_hpp = @pg_query($conn, $sql_hpp);
        if ($r_hpp) {
            $row_hpp = pg_fetch_assoc($r_hpp);
            $total_hpp = (float)($row_hpp['total_hpp'] ?? 0);
            $total_item_terjual = (int)($row_hpp['total_item'] ?? 0);
        }
        $pendapatan_bersih = $total_penjualan - $total_hpp;
        $margin_persen = $total_penjualan > 0 ? round(($pendapatan_bersih / $total_penjualan) * 100, 1) : 0;
    } elseif ($has_detail) {
        $r_items = @pg_query($conn, "SELECT COALESCE(SUM(COALESCE(d.jumlah, 0)), 0)::integer AS total_item
            FROM tbl_ikdt d
            JOIN tbl_ikhd h ON d.notransaksi = h.notransaksi
            WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
              AND h.tanggal::date <= '$tgl_selesai_esc'::date
              AND h.notrsretur IS NULL");
        if ($r_items) {
            $total_item_terjual = (int)pg_fetch_result($r_items, 0, 0);
        }
    }

    $hari_rentang = max(1, (strtotime($tgl_selesai) - strtotime($tgl_mulai)) / 86400 + 1);
    $rata_harian = $total_penjualan > 0 ? round($total_penjualan / $hari_rentang, 2) : 0;
    $rata_bersih_harian = ($hari_rentang > 0 && $pendapatan_bersih > 0) ? round($pendapatan_bersih / $hari_rentang, 2) : 0;

    // Recent 50 transactions
    $sql_recent = "SELECT
        h.notransaksi,
        h.tanggal::date AS tgl,
        h.totalakhir,
        COALESCE(sp.nama, '-') AS pelanggan,
        COALESCE(sp.kode, '-') AS kode_pelanggan
    FROM tbl_ikhd h
    LEFT JOIN tbl_supel sp ON h.kodesupel = sp.kode
    WHERE h.tanggal::date >= '$tgl_mulai_esc'::date
      AND h.tanggal::date <= '$tgl_selesai_esc'::date
      AND h.notrsretur IS NULL
      $exclude_pesanan
    ORDER BY h.tanggal DESC
    LIMIT 50";

    $r_recent = @pg_query($conn, $sql_recent);
    $transaksi_terbaru = [];
    if ($r_recent) {
        while ($row = pg_fetch_assoc($r_recent)) {
            $transaksi_terbaru[] = [
                'notransaksi' => $row['notransaksi'],
                'tgl' => $row['tgl'],
                'totalakhir' => (float)$row['totalakhir'],
                'pelanggan' => $row['pelanggan'],
                'kode_pelanggan' => $row['kode_pelanggan'],
            ];
        }
    }

    $data = [
        'success' => true,
        'data' => [
            'tgl_mulai' => $tgl_mulai,
            'tgl_selesai' => $tgl_selesai,
            'total_transaksi' => $total_transaksi,
            'total_penjualan' => $total_penjualan,
            'rata_rata_per_transaksi' => round($rata_rata, 2),
            'rata_rata_per_hari' => $rata_harian,
            'hari_rentang' => (int)$hari_rentang,
            'total_item_terjual' => $total_item_terjual,
            'transaksi_terbaru' => $transaksi_terbaru,
            'total_hpp' => $total_hpp,
            'pendapatan_bersih' => $pendapatan_bersih,
            'rata_rata_bersih_per_hari' => $rata_bersih_harian,
            'margin_persen' => $margin_persen,
            'can_calc_profit' => $can_profit,
            'has_detail_table' => $has_detail,
            'has_hpp_column' => $has_hpp,
            'available_date_range' => [
                'min_date' => $min_date,
                'max_date' => $max_date,
            ],
            'deductions' => [],
            'total_deductions_penjualan' => 0,
            'total_deductions_hpp' => 0,
            'pendapatan_bersih_non_ded' => $pendapatan_bersih,
            'margin_non_ded' => $margin_persen,
            'harian' => [],
        ],
        'synced_at' => date('Y-m-d H:i:s'),
    ];

    $written = writeDataCacheFile('cache_penghasilan.json', $data, 'penghasilan');
    $result['total_transaksi'] = $total_transaksi;
    $result['total_penjualan'] = $total_penjualan;
    log_and_echo("Sync penghasilan selesai: " . $total_transaksi . " transaksi, total Rp " . number_format($total_penjualan, 0, ',', '.'));
    return $result;
}

// ─── MAIN ───────────────────────────────────────────────────────────────────
function main(bool $watch_mode, int $watch_interval, bool $git_push, int $git_interval): void {
    echo "\n";
    echo "╔══════════════════════════════════════════════╗\n";
    echo "║  Royal Komputer — IPOS4 Auto Sync Agent      ║\n";
    echo "╚══════════════════════════════════════════════╝\n";
    echo "\n";
    log_and_echo("PHP version: " . PHP_VERSION);
    $git_label = $git_push ? " — Git push setiap " . ($git_interval >= 3600 ? ($git_interval/3600) . " jam" : $git_interval . " detik") : "";
    log_and_echo("Mode: " . ($watch_mode ? "WATCH (interval: {$watch_interval}s)" : "ONCE") . $git_label);
    log_and_echo("Memory limit: " . ini_get('memory_limit'));

    $iteration = 0;

    do {
        $iteration++;
        if ($watch_mode && $iteration > 1) {
            echo "\n";
            echo str_repeat("─", 50) . "\n";
            log_and_echo("Iterasi #$iteration — " . date('Y-m-d H:i:s'));
        }

        $start_mem = memory_get_usage(true);
        $sync_result = runSync();

        // ─── WRITE LAST SYNC ────────────────────────────────────────────────
        $peak_memory = memory_get_peak_usage(true);
        $last_sync = [
            'last_sync' => date('Y-m-d H:i:s'),
            'products' => $sync_result['products'],
            'duration' => $sync_result['duration'],
            'db_connected' => $sync_result['db_connected'],
            'photos_synced' => $sync_result['photos_synced'],
            'success' => $sync_result['success'],
            'peak_memory' => format_bytes($peak_memory),
            'error' => $sync_result['error'],
            'iteration' => $iteration,
        ];
        $sync_targets = [
            __DIR__ . '/last_sync.json',
            __DIR__ . '/../backend/data/last_sync.json',
        ];
        foreach ($sync_targets as $path) {
            file_put_contents($path, json_encode($last_sync, JSON_PRETTY_PRINT));
        }

        // ─── GIT PUSH ──────────────────────────────────────────────────────
        if ($git_push) {
            $elapsed = $iteration * $watch_interval;
            $last_git_file = __DIR__ . '/last_git_push.txt';
            $last_git_time = file_exists($last_git_file) ? (int)file_get_contents($last_git_file) : 0;
            $do_git = ($watch_mode && (time() - $last_git_time) >= $git_interval) || !$watch_mode;
            if ($do_git) {
                gitCommitAndPush();
                file_put_contents($last_git_file, (string) time());
            }
        }

        $status = $sync_result['success'] ? "OK" : "GAGAL";
        write_log("=== SYNC #{$iteration} $status ===");
        write_log("Produk: {$sync_result['products']} | DB: " . ($sync_result['db_connected'] ? 'terhubung' : 'offline') . " | Durasi: {$sync_result['duration']}s | Memori: " . format_bytes($peak_memory));

        echo "\n";
        $box_w = 43;
        echo "+" . str_repeat("-", $box_w - 2) . "+\n";
        echo "| " . str_pad("ITERASI #$iteration — $status", $box_w - 4, " ", STR_PAD_BOTH) . " |\n";
        echo "|" . str_repeat("-", $box_w - 2) . "|\n";
        echo "| Produk:     " . str_pad($sync_result['products'], $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
        echo "| Database:   " . str_pad($sync_result['db_connected'] ? 'Terhubung' : 'Offline', $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
        echo "| Durasi:     " . str_pad("{$sync_result['duration']}s", $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
        echo "| Memori:     " . str_pad(format_bytes($peak_memory), $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
        echo "+" . str_repeat("-", $box_w - 2) . "+\n";

        if ($watch_mode) {
            $next_run = date('H:i:s', time() + $watch_interval);
            echo "  Menunggu {$watch_interval}s... (berikutnya ~$next_run)\n";
            sleep($watch_interval);
            // Reset peak memory per iterasi
            memory_get_peak_usage(true);
        }
    } while ($watch_mode);

    echo "\n";
}

main($watch_mode, $watch_interval, $git_push, $git_interval);
