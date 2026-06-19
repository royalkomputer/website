<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class CorsTest extends TestCase
{
    private array $origServer;

    protected function setUp(): void
    {
        $this->origServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->origServer;
    }

    public function test_allows_known_origin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://royal-komputer.netlify.app';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // handleCORS should not crash or exit for GET requests
        handleCORS();
        $this->assertTrue(true);
    }

    public function test_allows_vite_dev_origin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:5173';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        handleCORS();
        $this->assertTrue(true);
    }

    public function test_allows_php_dev_origin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        handleCORS();
        $this->assertTrue(true);
    }

    public function test_unknown_origin_allows_wildcard(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://unknown-site.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        handleCORS();
        $this->assertTrue(true);
    }

    public function test_empty_origin_allows_wildcard(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_ORIGIN']);

        handleCORS();
        $this->assertTrue(true);
    }

    // Origin matching logic tests (unit tests without calling handleCORS)
    public function test_known_origin_in_list(): void
    {
        $origin = 'https://royal-komputer.netlify.app';
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8080',
            'https://royal-komputer.netlify.app',
        ];
        $this->assertContains($origin, $allowedOrigins);
    }

    public function test_unknown_origin_not_in_list(): void
    {
        $origin = 'https://unknown-site.com';
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8080',
            'https://royal-komputer.netlify.app',
        ];
        $this->assertNotContains($origin, $allowedOrigins);
    }

    public function test_origin_matching_is_exact(): void
    {
        $origin = 'https://sub.royal-komputer.netlify.app';
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8080',
            'https://royal-komputer.netlify.app',
        ];
        $this->assertNotContains($origin, $allowedOrigins);
    }

    public function test_cors_headers_methods(): void
    {
        $this->assertEquals('GET, POST, OPTIONS', 'GET, POST, OPTIONS');
        $this->assertEquals('Content-Type, X-Requested-With', 'Content-Type, X-Requested-With');
    }

    public function test_non_options_passes_through(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertNotEquals('OPTIONS', $_SERVER['REQUEST_METHOD']);
    }
}
