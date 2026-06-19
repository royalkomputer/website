<?php
/**
 * backend/tests/test_api.php — Royal Komputer API Test Suite (PHP)
 *
 * Usage:
 *   1. Start the PHP server:  php -S localhost:8081 -t backend/
 *   2. Run this script:       php backend/tests/test_api.php
 *
 * Environment variable overrides:
 *   BASE_URL=http://my-server.com php backend/tests/test_api.php
 *
 * Exit codes:
 *   0 = all tests passed
 *   1 = one or more tests failed
 */

$BASE_URL = getenv('BASE_URL') ?: 'http://localhost:8081';
$PASS = 0;
$FAIL = 0;
$FAILURES = [];

// ─────────────────────────────────────────────────────────────────────────────
//  Test Helpers
// ─────────────────────────────────────────────────────────────────────────────

function request(string $method, string $path, array $headers = []): array {
    global $BASE_URL;

    $opts = [
        'http' => [
            'method' => $method,
            'header' => array_merge(['Content-Type: application/json'], $headers),
            'ignore_errors' => true,
            'timeout' => 5,
        ]
    ];

    // For OPTIONS, we need to capture response headers
    if ($method === 'OPTIONS') {
        $opts['http']['header'][] = 'Origin: http://localhost:5173';
    }

    $context = stream_context_create($opts);
    $url = $BASE_URL . $path;
    $body = @file_get_contents($url, false, $context);

    // Get HTTP status code from $http_response_header
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $status = (int) ($matches[1] ?? 0);
    }

    // Get redirect URL if any
    $redirectUrl = '';
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (stripos($h, 'Location:') === 0) {
                $redirectUrl = trim(substr($h, 9));
            }
        }
    }

    return [
        'status' => $status,
        'body' => $body === false ? '' : $body,
        'redirect' => $redirectUrl,
        'headers' => $http_response_header ?? [],
    ];
}

function assertStatus(string $desc, string $method, string $path, int $expected): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request($method, $path);
    if ($res['status'] === $expected) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (expected HTTP $expected, got {$res['status']})\n";
        $FAIL++;
        $FAILURES[] = "$desc (HTTP {$res['status']}, expected $expected)";
    }
}

function assertJsonIsObject(string $desc, string $method, string $path): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request($method, $path);
    $data = json_decode($res['body'], true);
    if (is_array($data) && !isset($data[0])) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (response is not a JSON object)\n";
        $FAIL++;
        $FAILURES[] = "$desc (not a JSON object)";
    }
}

function assertJsonIsArray(string $desc, string $method, string $path): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request($method, $path);
    $data = json_decode($res['body'], true);
    if (is_array($data) && isset($data[0])) {
        echo "  ✓ $desc\n";
        $PASS++;
    } elseif (is_array($data) && empty($data)) {
        // Empty array is still an array
        echo "  ✓ $desc  (empty array)\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (response is not a JSON array)\n";
        $FAIL++;
        $FAILURES[] = "$desc (not a JSON array)";
    }
}

function assertJsonHasKey(string $desc, string $method, string $path, string $key): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request($method, $path);
    $data = json_decode($res['body'], true);
    if (is_array($data) && array_key_exists($key, $data)) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (key '$key' not found)\n";
        $FAIL++;
        $FAILURES[] = "$desc (key '$key' missing)";
    }
}

function assertJsonBoolField(string $desc, string $method, string $path, string $field): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request($method, $path);
    $data = json_decode($res['body'], true);
    if (is_array($data) && isset($data[$field]) && is_bool($data[$field])) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        $type = isset($data[$field]) ? gettype($data[$field]) : 'missing';
        echo "  ✗ $desc  (field '$field' is $type, not boolean)\n";
        $FAIL++;
        $FAILURES[] = "$desc (field '$field' not boolean)";
    }
}

function assertContains(string $desc, string $method, string $path, string $substring): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request($method, $path);
    if (str_contains($res['body'], $substring)) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (expected to contain '$substring')\n";
        $FAIL++;
        $FAILURES[] = "$desc (missing '$substring')";
    }
}

function assertRedirect(string $desc, string $path, string $expectedLocation): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request('GET', $path);
    if (str_contains($res['redirect'], $expectedLocation)) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (expected redirect to '$expectedLocation', got '{$res['redirect']}')\n";
        $FAIL++;
        $FAILURES[] = "$desc (redirect to '{$res['redirect']}')";
    }
}

function assertCorsHeader(string $desc, string $path): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request('OPTIONS', $path);
    $found = false;
    foreach ($res['headers'] as $h) {
        if (stripos($h, 'Access-Control-Allow-Origin') !== false) {
            $found = true;
            break;
        }
    }
    if ($found) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (no Access-Control-Allow-Origin header)\n";
        $FAIL++;
        $FAILURES[] = "$desc (no CORS header)";
    }
}

function assertContentType(string $desc, string $path, string $expectedContentType): void {
    global $PASS, $FAIL, $FAILURES;
    $res = request('GET', $path);
    $found = false;
    foreach ($res['headers'] as $h) {
        if (stripos($h, 'Content-Type') !== false && stripos($h, $expectedContentType) !== false) {
            $found = true;
            break;
        }
    }
    if ($found) {
        echo "  ✓ $desc\n";
        $PASS++;
    } else {
        echo "  ✗ $desc  (expected Content-Type containing '$expectedContentType')\n";
        $FAIL++;
        $FAILURES[] = "$desc (wrong Content-Type)";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Test Suite
// ─────────────────────────────────────────────────────────────────────────────

echo "\n";
echo "========================================================\n";
echo "  Royal Komputer — Backend API Test Suite\n";
echo "  Target: $BASE_URL\n";
echo "========================================================\n";
echo "\n";

// 1. Connectivity
echo "[1/6] Connectivity\n";
assertStatus('Server is reachable', 'GET', '/index.php', 200);

// 2. Health Check
echo "\n[2/6] Health Check (index.php)\n";
assertStatus('Health check returns 200', 'GET', '/index.php', 200);
assertJsonIsObject('Response is a JSON object', 'GET', '/index.php');
assertJsonHasKey('Has status key', 'GET', '/index.php', 'status');
assertJsonHasKey('Has database key', 'GET', '/index.php', 'database');
assertJsonHasKey('Has service key', 'GET', '/index.php', 'service');
assertJsonHasKey('Has time key', 'GET', '/index.php', 'time');

// 3. CORS
echo "\n[3/6] CORS Headers\n";
assertCorsHeader('api_produk.php returns CORS headers', '/api_produk.php');
assertCorsHeader('api_status.php returns CORS headers', '/api_status.php');
assertCorsHeader('api_schedules.php returns CORS headers', '/api_schedules.php');
assertStatus('api_produk.php OPTIONS returns 204', 'OPTIONS', '/api_produk.php', 204);
assertStatus('api_status.php OPTIONS returns 204', 'OPTIONS', '/api_status.php', 204);
assertStatus('api_schedules.php OPTIONS returns 204', 'OPTIONS', '/api_schedules.php', 204);

// 4. Store Status
echo "\n[4/6] Store Status (api_status.php)\n";
assertStatus('Store status returns 200', 'GET', '/api_status.php', 200);
assertJsonIsObject('Response is a JSON object', 'GET', '/api_status.php');
assertJsonHasKey('Has isOpen field', 'GET', '/api_status.php', 'isOpen');
assertJsonHasKey('Has isTemporarilyClosed field', 'GET', '/api_status.php', 'isTemporarilyClosed');
assertJsonHasKey('Has closeTime field', 'GET', '/api_status.php', 'closeTime');
assertJsonHasKey('Has hours field', 'GET', '/api_status.php', 'hours');
assertJsonHasKey('Has timestamp field', 'GET', '/api_status.php', 'timestamp');
assertJsonBoolField('isOpen is boolean', 'GET', '/api_status.php', 'isOpen');
assertJsonBoolField('isTemporarilyClosed is boolean', 'GET', '/api_status.php', 'isTemporarilyClosed');

// 5. Schedules
echo "\n[5/6] Schedules (api_schedules.php)\n";
assertStatus('Schedules returns 200', 'GET', '/api_schedules.php', 200);
assertJsonIsArray('Response is a JSON array', 'GET', '/api_schedules.php');

// 6. Auth Protection
echo "\n[6/6] Auth Protection\n";
assertStatus('admin.php redirects to login (302)', 'GET', '/admin.php', 302);
assertRedirect('admin.php redirects to login.php', '/admin.php', 'login.php');
assertContains('update_produk.php rejects unauthenticated', 'POST', '/update_produk.php', 'Akses ditolak');
assertContains('update_admin.php rejects unauthenticated', 'POST', '/update_admin.php', 'Akses ditolak');
assertContains('update_jam.php rejects unauthenticated', 'POST', '/update_jam.php', 'Akses ditolak');
assertContains('api_manage_photos.php rejects unauthenticated', 'POST', '/api_manage_photos.php', 'Akses ditolak');

// 7. Content Types
echo "\n[7/6] Content Type Headers\n";
assertContains('api_produk.php returns JSON', 'GET', '/api_produk.php', '{');
assertContains('api_status.php returns JSON', 'GET', '/api_status.php', '{');
assertContains('api_schedules.php returns JSON', 'GET', '/api_schedules.php', '[');
assertContains('login.php returns HTML', 'GET', '/login.php', '<!DOCTYPE');
assertContains('index.php returns JSON', 'GET', '/index.php', '{');

// ─────────────────────────────────────────────────────────────────────────────
//  Summary
// ─────────────────────────────────────────────────────────────────────────────

echo "\n";
echo "========================================================\n";
if ($FAIL === 0) {
    echo "  \033[32mALL $PASS TESTS PASSED\033[0m\n";
} else {
    echo "  \033[31m$FAIL TESTS FAILED\033[0m, \033[32m$PASS PASSED\033[0m\n";
    echo "\n";
    echo "  Failed tests:\n";
    foreach ($FAILURES as $f) {
        echo "    - $f\n";
    }
}
echo "========================================================\n";
echo "\n";

exit($FAIL === 0 ? 0 : 1);
