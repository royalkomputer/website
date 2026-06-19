<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class PhotoManagementTest extends TestCase
{
    private string $testUploadDir;

    protected function setUp(): void
    {
        $this->testUploadDir = sys_get_temp_dir() . '/royal_photos_' . bin2hex(random_bytes(4));
        mkdir($this->testUploadDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir_recursive($this->testUploadDir);
    }

    private function rmdir_recursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    // -------------------------------------------------------
    // Safe Kode Generation
    // -------------------------------------------------------
    public function test_safe_kode_replaces_special_chars(): void
    {
        $id = 'BRG-001/A';
        $safe = preg_replace('/[^A-Za-z0-9]/', '_', $id);
        $this->assertEquals('BRG_001_A', $safe);
    }

    public function test_safe_kode_preserves_alphanumeric(): void
    {
        $id = 'BRG001';
        $safe = preg_replace('/[^A-Za-z0-9]/', '_', $id);
        $this->assertEquals('BRG001', $safe);
    }

    public function test_safe_kode_with_spaces(): void
    {
        $id = 'PROD 123 ABC';
        $safe = preg_replace('/[^A-Za-z0-9]/', '_', $id);
        $this->assertEquals('PROD_123_ABC', $safe);
    }

    public function test_safe_kode_with_dots(): void
    {
        $id = 'item.1.2';
        $safe = preg_replace('/[^A-Za-z0-9]/', '_', $id);
        $this->assertEquals('item_1_2', $safe);
    }

    // -------------------------------------------------------
    // File Naming Pattern — Multi-Format Support
    // -------------------------------------------------------
    public function test_multi_format_glob_returns_all_extensions(): void
    {
        $safe_kode = 'BRG_001';
        $files = [
            $this->testUploadDir . '/' . $safe_kode . '_1.webp',
            $this->testUploadDir . '/' . $safe_kode . '_2.jpg',
            $this->testUploadDir . '/' . $safe_kode . '_3.png',
            $this->testUploadDir . '/' . $safe_kode . '_4.gif',
        ];

        foreach ($files as $f) {
            file_put_contents($f, 'dummy');
        }

        $matched_files = [];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($this->testUploadDir . '/' . $safe_kode . '_*.' . $ext);
            if ($matches) $matched_files = array_merge($matched_files, $matches);
        }
        sort($matched_files);

        $this->assertCount(4, $matched_files);
    }

    public function test_mixed_formats_in_glob(): void
    {
        $safe_kode = 'BRG_001';
        file_put_contents($this->testUploadDir . '/' . $safe_kode . '_1.webp', 'dummy');
        file_put_contents($this->testUploadDir . '/' . $safe_kode . '_2.jpg', 'dummy');
        file_put_contents($this->testUploadDir . '/' . $safe_kode . '_3.png', 'dummy');

        $matched_files = [];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($this->testUploadDir . '/' . $safe_kode . '_*.' . $ext);
            if ($matches) $matched_files = array_merge($matched_files, $matches);
        }
        sort($matched_files);

        $basenames = array_map('basename', $matched_files);
        $this->assertEquals([
            $safe_kode . '_1.webp',
            $safe_kode . '_2.jpg',
            $safe_kode . '_3.png',
        ], $basenames);
    }

    public function test_legacy_single_photo_any_format(): void
    {
        $safe_kode = 'BRG_001';
        $legacy = $this->testUploadDir . '/' . $safe_kode . '.jpg';
        file_put_contents($legacy, 'dummy');

        $matched_files = [];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($this->testUploadDir . '/' . $safe_kode . '_*.' . $ext);
            if ($matches) $matched_files = array_merge($matched_files, $matches);
        }
        sort($matched_files);

        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $legacy_file = $this->testUploadDir . '/' . $safe_kode . '.' . $ext;
            if (file_exists($legacy_file)) {
                array_unshift($matched_files, $legacy_file);
                break;
            }
        }

        $this->assertCount(1, $matched_files);
        $this->assertEquals($legacy, $matched_files[0]);
    }

    public function test_legacy_and_multi_format_coexist(): void
    {
        $safe_kode = 'BRG_001';
        $legacy = $this->testUploadDir . '/' . $safe_kode . '.jpg';
        $multi1 = $this->testUploadDir . '/' . $safe_kode . '_1.webp';
        $multi2 = $this->testUploadDir . '/' . $safe_kode . '_2.png';

        file_put_contents($legacy, 'dummy');
        file_put_contents($multi1, 'dummy');
        file_put_contents($multi2, 'dummy');

        $matched_files = [];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($this->testUploadDir . '/' . $safe_kode . '_*.' . $ext);
            if ($matches) $matched_files = array_merge($matched_files, $matches);
        }
        sort($matched_files);

        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $legacy_file = $this->testUploadDir . '/' . $safe_kode . '.' . $ext;
            if (file_exists($legacy_file)) {
                array_unshift($matched_files, $legacy_file);
                break;
            }
        }

        $this->assertCount(3, $matched_files);
        $this->assertEquals($legacy, $matched_files[0]); // legacy first
    }

    // -------------------------------------------------------
    // Image URL Generation
    // -------------------------------------------------------
    public function test_cache_busting_query_string(): void
    {
        $file = $this->testUploadDir . '/test.webp';
        file_put_contents($file, 'dummy');
        $mtime = filemtime($file);

        $url = 'uploads/test.webp?v=' . $mtime;
        $this->assertStringStartsWith('uploads/test.webp?v=', $url);
        $this->assertEquals((string)$mtime, explode('v=', $url)[1]);
    }

    public function test_default_image_fallback(): void
    {
        $default_img = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500';

        $matched_files = []; // no photos found
        if (!empty($matched_files)) {
            $image = $matched_files[0];
        } else {
            $image = $default_img;
            $images = [$default_img];
        }

        $this->assertEquals($default_img, $image);
        $this->assertEquals([$default_img], $images ?? []);
    }

    // -------------------------------------------------------
    // Realpath Security Validation (from api_manage_photos.php)
    // -------------------------------------------------------
    public function test_realpath_prevents_traversal(): void
    {
        $target_dir = $this->testUploadDir . '/';
        $malicious = $this->testUploadDir . '/../../../etc/passwd';

        $this->assertFalse(is_file($malicious));

        if (is_file($malicious)) {
            $real = realpath($malicious);
            $allowed = $real !== false && strpos($real, realpath($target_dir)) === 0;
            $this->assertFalse($allowed);
        }
    }

    public function test_realpath_allows_valid_file(): void
    {
        $target_dir = $this->testUploadDir . '/';
        $valid_file = $this->testUploadDir . '/BRG_001_1.webp';
        file_put_contents($valid_file, 'dummy');

        $real = realpath($valid_file);
        $allowed = $real !== false && strpos($real, realpath($target_dir)) === 0;
        $this->assertTrue($allowed);
    }

    public function test_file_must_belong_to_product(): void
    {
        $target_dir = $this->testUploadDir . '/';
        $safe_kode = 'BRG_001';

        $valid = $this->testUploadDir . '/' . $safe_kode . '_1.webp';
        $invalid = $this->testUploadDir . '/OTHER_001_1.webp';

        file_put_contents($valid, 'dummy');
        file_put_contents($invalid, 'dummy');

        // Check prefix match
        $this->assertStringStartsWith($safe_kode, basename($valid));
        $this->assertFalse(str_starts_with(basename($invalid), $safe_kode));
    }

    // -------------------------------------------------------
    // Reorder Logic
    // -------------------------------------------------------
    public function test_reorder_preserves_extensions(): void
    {
        $safe_kode = 'BRG_001';
        $files = [
            'old_3.jpg' => $this->testUploadDir . '/' . $safe_kode . '_3.jpg',
            'old_1.webp' => $this->testUploadDir . '/' . $safe_kode . '_1.webp',
            'old_2.png' => $this->testUploadDir . '/' . $safe_kode . '_2.png',
        ];
        foreach ($files as $path) {
            file_put_contents($path, 'dummy');
        }

        $ordered = [
            ['basename' => 'BRG_001_3.jpg',  'path' => $files['old_3.jpg']],
            ['basename' => 'BRG_001_1.webp', 'path' => $files['old_1.webp']],
            ['basename' => 'BRG_001_2.png',  'path' => $files['old_2.png']],
        ];

        // Simulate reorder: rename all to temp preserving extension
        $temp_map = [];
        foreach ($ordered as $item) {
            $ext = pathinfo($item['path'], PATHINFO_EXTENSION);
            $temp = $this->testUploadDir . '/temp_' . uniqid() . '.' . $ext;
            rename($item['path'], $temp);
            $temp_map[] = ['temp' => $temp, 'ext' => $ext];
        }

        // Rename to final numbered order
        $index = 1;
        foreach ($temp_map as $entry) {
            $final = $this->testUploadDir . '/' . $safe_kode . '_' . $index . '.' . $entry['ext'];
            rename($entry['temp'], $final);
            $index++;
        }

        // Verify final state
        $matched = [];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $matches = glob($this->testUploadDir . '/' . $safe_kode . '_*.' . $ext);
            if ($matches) $matched = array_merge($matched, $matches);
        }
        sort($matched);
        $basenames = array_map('basename', $matched);
        $this->assertEquals([
            $safe_kode . '_1.jpg',
            $safe_kode . '_2.webp',
            $safe_kode . '_3.png',
        ], $basenames);
    }

    // -------------------------------------------------------
    // Delete Logic
    // -------------------------------------------------------
    public function test_delete_removes_file(): void
    {
        $file = $this->testUploadDir . '/BRG_001_1.webp';
        file_put_contents($file, 'dummy');
        $this->assertFileExists($file);

        unlink($file);
        $this->assertFileDoesNotExist($file);
    }

    public function test_delete_only_own_product_files(): void
    {
        $safe_kode = 'BRG_001';
        $own_file = $this->testUploadDir . '/' . $safe_kode . '_1.webp';
        $other_file = $this->testUploadDir . '/OTHER_001_1.webp';

        file_put_contents($own_file, 'dummy');
        file_put_contents($other_file, 'dummy');

        $basename = basename($own_file);
        $belongs = strpos($basename, $safe_kode) === 0;
        $this->assertTrue($belongs);

        $basename = basename($other_file);
        $belongs = strpos($basename, $safe_kode) === 0;
        $this->assertFalse($belongs);
    }

    // -------------------------------------------------------
    // Photo Sync Logic (from sync/update_produk.php)
    // -------------------------------------------------------
    public function test_photo_sync_copies_newer_files(): void
    {
        $src_dir = $this->testUploadDir . '/src/';
        $dst_dir = $this->testUploadDir . '/dst/';
        mkdir($src_dir);
        mkdir($dst_dir);

        // Source has a photo
        file_put_contents($src_dir . 'photo.webp', 'source');
        $src_mtime = filemtime($src_dir . 'photo.webp');

        // Destination has older version
        file_put_contents($dst_dir . 'photo.webp', 'old_destination');
        touch($dst_dir . 'photo.webp', $src_mtime - 3600); // 1 hour older

        // Should sync because source is newer
        $should_sync = !file_exists($dst_dir . 'photo.webp') || $src_mtime > filemtime($dst_dir . 'photo.webp');
        $this->assertTrue($should_sync);

        if ($should_sync) {
            copy($src_dir . 'photo.webp', $dst_dir . 'photo.webp');
        }

        $this->assertEquals('source', file_get_contents($dst_dir . 'photo.webp'));
    }

    public function test_photo_sync_skips_up_to_date(): void
    {
        $src_dir = $this->testUploadDir . '/src/';
        $dst_dir = $this->testUploadDir . '/dst/';
        mkdir($src_dir);
        mkdir($dst_dir);

        file_put_contents($src_dir . 'photo.webp', 'source');
        file_put_contents($dst_dir . 'photo.webp', 'destination');
        $src_mtime = filemtime($src_dir . 'photo.webp');
        $dst_mtime = filemtime($dst_dir . 'photo.webp');

        // If both same mtime, no sync needed
        touch($dst_dir . 'photo.webp', $src_mtime);
        $should_sync = !file_exists($dst_dir . 'photo.webp') || $src_mtime > filemtime($dst_dir . 'photo.webp');
        $this->assertFalse($should_sync);
    }

    public function test_photo_sync_creates_destination_dir(): void
    {
        $srcDir = $this->testUploadDir . '/src/';
        $dst_dir = $this->testUploadDir . '/dst_new/';
        mkdir($srcDir);

        file_put_contents($srcDir . 'photo.webp', 'dummy');

        if (!is_dir($dst_dir)) {
            mkdir($dst_dir, 0777, true);
        }

        $this->assertDirectoryExists($dst_dir);
    }
}
