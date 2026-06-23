<?php
/**
 * sync/update_produk.php — Headless IPOS Sync Agent
 *
 * Runs on the local PC via Windows Task Scheduler every 1 hour.
 * Queries the IPOS PostgreSQL database for products with stock > 0,
 * generates cache_produk.json, and writes it to sync/, frontend/, and backend/data/.
 *
 * After this script completes, git_push.bat commits and pushes changes.
 *
 * Usage: php sync/update_produk.php
 * Log:   sync/sync.log
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once __DIR__ . '/config.php';

$log_file = __DIR__ . '/sync.log';
$start_time = microtime(true);

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

echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║  Royal Komputer — IPOS Sync Agent       ║\n";
echo "╚══════════════════════════════════════════╝\n";
echo "\n";

log_and_echo("PHP version: " . PHP_VERSION);
log_and_echo("Memory limit: " . ini_get('memory_limit'));
log_and_echo("Working directory: " . __DIR__);

// --- SYNC PHOTOS FROM BACKEND TO FRONTEND ---
echo "─── Photo Sync ───────────────────────────────\n";
log_and_echo("Checking backend/uploads/ for new photos...");
$backend_uploads = __DIR__ . '/../backend/uploads/';
$frontend_uploads = __DIR__ . '/../frontend/uploads/';

if (!is_dir($backend_uploads)) {
    log_and_echo("WARNING — backend/uploads/ does not exist at: " . $backend_uploads);
} else {
    if (!is_dir($frontend_uploads)) {
        mkdir($frontend_uploads, 0777, true);
        log_and_echo("Created frontend/uploads/ directory");
    }

    $all_photos = [];
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $matches = glob($backend_uploads . '*.' . $ext);
        if ($matches) $all_photos = array_merge($all_photos, $matches);
    }
    $photo_count = count($all_photos);
    log_and_echo("Found $photo_count image files in backend/uploads/");

    $synced = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($all_photos as $photo) {
        $filename = basename($photo);
        $dest = $frontend_uploads . $filename;
        $backend_mtime = filemtime($photo);

        if (!file_exists($dest) || $backend_mtime > filemtime($dest)) {
            $result = copy($photo, $dest);
            if ($result) {
                $synced++;
                write_log("[PHOTO SYNC] Copied: $filename (mtime: $backend_mtime, size: " . format_bytes(filesize($photo)) . ")");
            } else {
                $failed++;
                write_log("[PHOTO SYNC] FAILED to copy: $filename");
            }
        } else {
            $skipped++;
        }
    }

    log_and_echo("Result: $synced copied, $skipped up-to-date, $failed failed");
}

// --- DATABASE CONNECTION ---
echo "─── Database ─────────────────────────────────\n";
log_and_echo("Connecting to PostgreSQL: host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME);
$conn = getDBConnection();

if (!$conn) {
    $err = error_get_last();
    $err_msg = $err ? $err['message'] : 'Unknown error';
    log_and_echo("FAIL — Could not connect to database: $err_msg");
    exit(1);
}
log_and_echo("Connection established successfully");

// --- QUERY PRODUK DENGAN STOK > 0 ---
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

log_and_echo("Executing product query...");
$query_start = microtime(true);
$result = @pg_query($conn, $sql);
$query_duration = round(microtime(true) - $query_start, 4);

if (!$result) {
    $err = pg_last_error($conn);
    log_and_echo("FAIL — Query error after {$query_duration}s: $err");
    pg_close($conn);
    exit(1);
}

$rows = pg_num_rows($result);
log_and_echo("Query returned $rows rows in {$query_duration}s");

// --- PROCESS PRODUCTS ---
echo "─── Processing ───────────────────────────────\n";
$produk = [];
$image_counts = [];
$category_counts = [];
$no_image_count = 0;

while ($row = pg_fetch_assoc($result)) {
    $row['price'] = (float) $row['price'];
    $row['stock'] = (float) $row['stock'];
    if (empty(trim($row['category']))) {
        $row['category'] = 'Lainnya';
    }

    // Track category distribution
    $cat = $row['category'];
    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;

    // --- GENERATE IMAGE PATHS ---
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $row['id']);
    $uploads_path = __DIR__ . '/../frontend/uploads/';
    $images = [];

    $matched_files = [];
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $matches = glob($uploads_path . $safe_kode . '_*.' . $ext);
        if ($matches) $matched_files = array_merge($matched_files, $matches);
    }
    sort($matched_files);
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $legacy_file = $uploads_path . $safe_kode . '.' . $ext;
        if (file_exists($legacy_file)) {
            array_unshift($matched_files, $legacy_file);
            break;
        }
    }

    if (!empty($matched_files)) {
        $img_count = count($matched_files);
        $image_counts[$img_count] = ($image_counts[$img_count] ?? 0) + 1;
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
$process_duration = round(microtime(true) - $query_start, 4);

log_and_echo("Processed " . count($produk) . " products in {$process_duration}s");
log_and_echo("Products with no photos: $no_image_count");

ksort($image_counts);
$img_summary = [];
foreach ($image_counts as $count => $num) {
    $img_summary[] = "$count photo(s): $num products";
}
if (empty($img_summary)) {
    log_and_echo("Image distribution: no products have photos");
} else {
    log_and_echo("Image distribution: " . implode(", ", $img_summary));
}

$cat_summary = [];
foreach ($category_counts as $cat => $count) {
    $cat_summary[] = "$cat: $count";
}
log_and_echo("Category distribution: " . implode(", ", $cat_summary));

// --- WRITE CACHE FILES ---
echo "─── Cache Files ──────────────────────────────\n";
$json_output = json_encode($produk);

if ($json_output === false) {
    log_and_echo("FAIL — JSON encoding error: " . json_last_error_msg());
    exit(1);
}

$targets = [
    __DIR__ . '/cache_produk.json'             => 'sync/',
    __DIR__ . '/../frontend/cache_produk.json'  => 'frontend/',
    __DIR__ . '/../backend/data/cache_produk.json' => 'backend/data/',
];

foreach ($targets as $path => $label) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        log_and_echo("Created directory: $dir");
    }
    $written = file_put_contents($path, $json_output);
    if ($written === false) {
        log_and_echo("FAIL — Could not write to $label");
    } else {
        log_and_echo("Written to $label: " . format_bytes($written));
    }
}

// --- WRITE LAST SYNC TIMESTAMP ---
$total_duration = round(microtime(true) - $start_time, 4);
$peak_memory = memory_get_peak_usage(true);
$count = count($produk);

$last_sync = [
    'last_sync' => date('Y-m-d H:i:s'),
    'products' => $count,
    'duration' => $total_duration,
    'peak_memory' => format_bytes($peak_memory),
    'photos_synced' => $synced ?? 0,
];
$sync_targets = [
    __DIR__ . '/last_sync.json',
    __DIR__ . '/../backend/data/last_sync.json',
];
foreach ($sync_targets as $path) {
    file_put_contents($path, json_encode($last_sync, JSON_PRETTY_PRINT));
}

write_log("=== SYNC COMPLETE ===");
write_log("Products: $count | Duration: {$total_duration}s | Peak memory: " . format_bytes($peak_memory));

echo "\n";
$box_w = 43;
echo "+" . str_repeat("-", $box_w - 2) . "+\n";
echo "| SYNC COMPLETE" . str_repeat(" ", $box_w - 15) . "|\n";
echo "|" . str_repeat("-", $box_w - 2) . "|\n";
echo "| Products:    " . str_pad($count, $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
echo "| Duration:    " . str_pad("{$total_duration}s", $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
echo "| Peak memory: " . str_pad(format_bytes($peak_memory), $box_w - 17, " ", STR_PAD_LEFT) . " |\n";
echo "+" . str_repeat("-", $box_w - 2) . "+\n";
echo "\n";
