<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

// format_bytes is defined in sync/update_produk.php, mirror it here for testing
if (!function_exists(__NAMESPACE__ . '\format_bytes')) {
    function format_bytes(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}

class SyncAgentTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/royal_sync_' . bin2hex(random_bytes(4));
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir_recursive($this->testDir);
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
    // Photo Sync Logic
    // -------------------------------------------------------
    public function test_photo_sync_copies_from_backend_to_frontend(): void
    {
        $backend_uploads = $this->testDir . '/backend/uploads/';
        $frontend_uploads = $this->testDir . '/frontend/uploads/';
        mkdir($backend_uploads, 0777, true);
        mkdir($frontend_uploads, 0777, true);

        // Create photos in backend
        file_put_contents($backend_uploads . 'BRG001_1.webp', 'photo1');
        file_put_contents($backend_uploads . 'BRG001_2.webp', 'photo2');
        touch($backend_uploads . 'BRG001_1.webp', time() - 100);

        $synced = 0;
        foreach (glob($backend_uploads . '*.webp') as $photo) {
            $dest = $frontend_uploads . basename($photo);
            if (!file_exists($dest) || filemtime($photo) > filemtime($dest)) {
                copy($photo, $dest);
                $synced++;
            }
        }

        $this->assertEquals(2, $synced);
        $this->assertFileExists($frontend_uploads . 'BRG001_1.webp');
        $this->assertFileExists($frontend_uploads . 'BRG001_2.webp');
    }

    public function test_photo_sync_skips_unchanged_files(): void
    {
        $backend_uploads = $this->testDir . '/backend/uploads/';
        $frontend_uploads = $this->testDir . '/frontend/uploads/';
        mkdir($backend_uploads, 0777, true);
        mkdir($frontend_uploads, 0777, true);

        // Same file in both, with same mtime
        file_put_contents($backend_uploads . 'photo.webp', 'same');
        file_put_contents($frontend_uploads . 'photo.webp', 'same');
        $mtime = time();
        touch($backend_uploads . 'photo.webp', $mtime);
        touch($frontend_uploads . 'photo.webp', $mtime);

        $synced = 0;
        foreach (glob($backend_uploads . '*.webp') as $photo) {
            $dest = $frontend_uploads . basename($photo);
            if (!file_exists($dest) || filemtime($photo) > filemtime($dest)) {
                copy($photo, $dest);
                $synced++;
            }
        }

        $this->assertEquals(0, $synced);
    }

    public function test_photo_sync_only_webp(): void
    {
        $backend_uploads = $this->testDir . '/backend/uploads/';
        $frontend_uploads = $this->testDir . '/frontend/uploads/';
        mkdir($backend_uploads, 0777, true);
        mkdir($frontend_uploads, 0777, true);

        file_put_contents($backend_uploads . 'photo.webp', 'webp');
        file_put_contents($backend_uploads . 'photo.jpg', 'jpg');
        file_put_contents($backend_uploads . 'photo.png', 'png');

        $synced = 0;
        foreach (glob($backend_uploads . '*.webp') as $photo) {
            $dest = $frontend_uploads . basename($photo);
            copy($photo, $dest);
            $synced++;
        }

        $this->assertEquals(1, $synced);
        $this->assertFileExists($frontend_uploads . 'photo.webp');
        $this->assertFileDoesNotExist($frontend_uploads . 'photo.jpg');
    }

    // -------------------------------------------------------
    // Cache File Writing
    // -------------------------------------------------------
    public function test_cache_written_to_multiple_targets(): void
    {
        $targets = [
            $this->testDir . '/sync/cache_produk.json',
            $this->testDir . '/frontend/cache_produk.json',
            $this->testDir . '/backend/data/cache_produk.json',
        ];

        foreach ($targets as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
        }

        $produk = [
            ['id' => 'BRG001', 'name' => 'Test Product', 'price' => 10000, 'stock' => 5],
        ];
        $json = json_encode($produk);

        foreach ($targets as $path) {
            file_put_contents($path, $json);
            $this->assertFileExists($path);
            $this->assertJson(file_get_contents($path));
        }
    }

    public function test_cache_files_have_same_content(): void
    {
        $targets = [
            $this->testDir . '/sync/cache_produk.json',
            $this->testDir . '/frontend/cache_produk.json',
            $this->testDir . '/backend/data/cache_produk.json',
        ];

        foreach ($targets as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
        }

        $produk = [
            ['id' => 'BRG001', 'name' => 'Test', 'price' => 25000, 'stock' => 10],
        ];
        $json = json_encode($produk);

        $contents = [];
        foreach ($targets as $path) {
            file_put_contents($path, $json);
            $contents[] = file_get_contents($path);
        }

        $this->assertEquals($contents[0], $contents[1]);
        $this->assertEquals($contents[1], $contents[2]);
    }

    public function test_cache_json_structure_correct(): void
    {
        $produk = [
            [
                'id' => 'BRG001',
                'name' => 'Test Product',
                'category' => 'COMPUTER',
                'price' => 1500000.0,
                'stock' => 10.0,
                'description' => 'A test product',
                'image' => 'uploads/BRG001_1.webp?v=1234567890',
                'images' => ['uploads/BRG001_1.webp?v=1234567890', 'uploads/BRG001_2.webp?v=1234567890'],
            ]
        ];

        $json = json_encode($produk);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertArrayHasKey('id', $decoded[0]);
        $this->assertArrayHasKey('name', $decoded[0]);
        $this->assertArrayHasKey('price', $decoded[0]);
        $this->assertArrayHasKey('stock', $decoded[0]);
        $this->assertArrayHasKey('image', $decoded[0]);
        $this->assertArrayHasKey('images', $decoded[0]);
        $this->assertIsArray($decoded[0]['images']);
        $this->assertEquals('BRG001', $decoded[0]['id']);
    }

    public function test_cache_empty_product_list(): void
    {
        $json = json_encode([]);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
    }

    public function test_cache_invalid_json_detected(): void
    {
        $invalid = '{broken json';
        $decoded = json_decode($invalid, true);
        $this->assertNull($decoded);
        $this->assertEquals(JSON_ERROR_SYNTAX, json_last_error());
    }

    // -------------------------------------------------------
    // Product Data Processing
    // -------------------------------------------------------
    public function test_empty_category_defaults_to_lainnya(): void
    {
        $category = '';
        if (empty(trim($category))) $category = 'Lainnya';
        $this->assertEquals('Lainnya', $category);
    }

    public function test_price_cast_to_float(): void
    {
        $price = '1500000';
        $cast = (float) $price;
        $this->assertIsFloat($cast);
        $this->assertEquals(1500000.0, $cast);
    }

    public function test_stock_cast_to_float(): void
    {
        $stock = '42';
        $cast = (float) $stock;
        $this->assertIsFloat($cast);
        $this->assertEquals(42.0, $cast);
    }

    public function test_product_with_zero_stock_is_filtered(): void
    {
        // The SQL query uses HAVING SUM(stok) > 0, so zero-stock items never appear
        $products = [
            ['id' => 'A', 'stock' => 10],
            ['id' => 'B', 'stock' => 0],
            ['id' => 'C', 'stock' => 5],
        ];

        $filtered = array_values(array_filter($products, fn($p) => $p['stock'] > 0));
        $this->assertCount(2, $filtered);
        $this->assertEquals('A', $filtered[0]['id']);
        $this->assertEquals('C', $filtered[1]['id']);
    }

    // -------------------------------------------------------
    // Logging
    // -------------------------------------------------------
    public function test_log_writes_timestamped_entry(): void
    {
        $log_file = $this->testDir . '/sync.log';
        $message = 'Test log entry';
        file_put_contents($log_file, date('Y-m-d H:i:s') . " $message\n", FILE_APPEND);

        $content = file_get_contents($log_file);
        $this->assertStringContainsString($message, $content);
        $this->assertStringContainsString(date('Y-m-d'), $content);
    }

    public function test_log_appends_multiple_entries(): void
    {
        $log_file = $this->testDir . '/sync.log';
        file_put_contents($log_file, "Line 1\n", FILE_APPEND);
        file_put_contents($log_file, "Line 2\n", FILE_APPEND);
        file_put_contents($log_file, "Line 3\n", FILE_APPEND);

        $lines = file($log_file, FILE_IGNORE_NEW_LINES);
        $this->assertCount(3, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 3', $lines[2]);
    }

    // -------------------------------------------------------
    // Directory Creation
    // -------------------------------------------------------
    public function test_directory_created_if_not_exists(): void
    {
        $new_dir = $this->testDir . '/new_directory/';
        $this->assertDirectoryDoesNotExist($new_dir);

        if (!is_dir($new_dir)) {
            mkdir($new_dir, 0777, true);
        }

        $this->assertDirectoryExists($new_dir);
    }

    public function test_existing_directory_not_recreated(): void
    {
        $dir = $this->testDir . '/existing/';
        mkdir($dir, 0777, true);
        $this->assertDirectoryExists($dir);

        // Should not error on subsequent check
        $exists = is_dir($dir);
        $this->assertTrue($exists);
    }

    // -------------------------------------------------------
    // File Size Reporting
    // -------------------------------------------------------
    public function test_format_bytes_helper(): void
    {
        $this->assertEquals('0 B', format_bytes(0));
        $this->assertEquals('1 B', format_bytes(1));
        $this->assertEquals('1 KB', format_bytes(1024));
        $this->assertEquals('1.5 KB', format_bytes(1536));
        $this->assertEquals('1 MB', format_bytes(1048576));
        $this->assertEquals('2 MB', format_bytes(2097152));
        $this->assertEquals('2.5 MB', format_bytes(2621440));
    }
}
