<?php
/**
 * Convert all non-WebP images in uploads/ to WebP at 60% quality.
 * Run from the command line: php convert_to_webp.php
 * Or via the companion .bat file (double-click).
 */

$dir = __DIR__ . '/uploads/';

if (!is_dir($dir)) {
    echo "ERROR: uploads/ folder not found at: $dir\n";
    exit(1);
}

$extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
$total = 0;
$converted = 0;
$skipped = 0;
$errors = 0;

foreach ($extensions as $ext) {
    $files = glob($dir . '*.' . $ext);
    foreach ($files as $file) {
        $total++;
        $info = getimagesize($file);
        if (!$info) {
            $errors++;
            echo "  SKIP (unreadable): " . basename($file) . "\n";
            continue;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg': $img = imagecreatefromjpeg($file); break;
            case 'image/png':
                $img = imagecreatefrompng($file);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                break;
            case 'image/gif': $img = imagecreatefromgif($file); break;
            case 'image/bmp': $img = imagecreatefrombmp($file); break;
            default: $img = null;
        }

        if (!$img) {
            $errors++;
            echo "  SKIP (unsupported format): " . basename($file) . "\n";
            continue;
        }

        $webp = preg_replace('/\.' . $ext . '$/i', '.webp', $file);
        if (file_exists($webp)) {
            $skipped++;
            imagedestroy($img);
            continue;
        }

        $ok = imagewebp($img, $webp, 60);
        imagedestroy($img);

        if ($ok) {
            $converted++;
            $size_before = filesize($file);
            $size_after = filesize($webp);
            $ratio = $size_before > 0 ? round((1 - $size_after / $size_before) * 100) : 0;
            unlink($file);
            echo "  OK: " . basename($file) . " -> " . basename($webp) . " (-$ratio%) (original deleted)\n";
        } else {
            $errors++;
            echo "  FAIL: " . basename($file) . "\n";
        }
    }
}

echo "\n============================================\n";
echo "  Total: $total | Converted: $converted | Skipped: $skipped | Errors: $errors\n";
echo "============================================\n";
