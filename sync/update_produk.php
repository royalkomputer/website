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
            LEFT JOIN tbl_web_deskripsi w ON i.kodeitem = w.kodeitem";

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

    pg_close($conn);
    $result['products'] = count($produk);
    log_and_echo("Diproses " . count($produk) . " produk (" . $no_image_count . " tanpa foto)");

    // ─── WRITE CACHE ────────────────────────────────────────────────────────
    $result['cache_written'] = writeCacheFiles($produk);
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
