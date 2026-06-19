# Backend API Test Suite

Test scripts untuk memverifikasi semua endpoint backend Royal Komputer.

## Prerequisites

- **PHP 8.x** with `pgsql` and `gd` extensions
- **curl** (for bash test script)
- **Python 3** (for JSON parsing in bash script)
- Server running (see below)

## Quick Start

### 1. Start the PHP development server

```bash
cd backend
php -S localhost:8081
```

Biarkan terminal ini berjalan. Server akan melayani request di `http://localhost:8081`.

### 2. Run the tests

#### Option A: PHP test script (recommended)

```bash
# From project root:
php backend/tests/test_api.php

# Or against a different URL:
BASE_URL=https://royal-backend.onrender.com php backend/tests/test_api.php
```

#### Option B: Bash/curl test script

```bash
# From project root:
bash backend/tests/test_api.sh

# Or against a different URL:
BASE_URL=https://royal-backend.onrender.com bash backend/tests/test_api.sh
```

## Test Coverage

| # | Category | What's Tested |
|---|----------|---------------|
| 1 | **Connectivity** | Server is reachable (HTTP 200) |
| 2 | **Health Check** | `GET /index.php` returns valid JSON with status, database, service, time |
| 3 | **CORS Headers** | All public APIs return `Access-Control-Allow-Origin`, OPTIONS returns 204 |
| 4 | **Store Status** | `GET /api_status.php` returns valid JSON with isOpen, isTemporarilyClosed, hours, timestamp |
| 5 | **Schedules** | `GET /api_schedules.php` returns a JSON array |
| 6 | **Auth Protection** | Admin-only endpoints reject unauthenticated requests with proper HTTP redirects or JSON error messages |
| 7 | **Content Types** | Each endpoint returns the correct Content-Type (JSON for APIs, HTML for login) |

## Writing New Tests

Ikuti pola yang sudah ada di `test_api.php`:

```php
// Test HTTP status
assertStatus('Description of test', 'GET', '/path', 200);

// Test JSON structure
assertJsonIsObject('Response is object', 'GET', '/path');
assertJsonHasKey('Has field', 'GET', '/path', 'fieldName');

// Test boolean fields
assertJsonBoolField('isOpen is boolean', 'GET', '/path', 'isOpen');

// Test string content
assertContains('Contains text', 'GET', '/path', 'expected text');

// Test redirects
assertStatus('Redirects to login', 'GET', '/admin.php', 302);
assertRedirect('Admin redirect', '/admin.php', 'login.php');

// Test CORS
assertCorsHeader('Has CORS headers', '/api_produk.php');
```

## Expected Results When Running Locally

When running locally without a database connection, some tests will still pass because the API handles database failures gracefully:

- **Health check**: Returns `{"status":"ok","database":"disconnected"}` → ✅ passes
- **Product API**: Falls back to `cache_produk.json` → may pass if cache exists
- **Store status**: Uses local JSON files → ✅ passes
- **Schedules**: Reads from local JSON → ✅ passes
- **Auth endpoints**: Return JSON error (not logged in) → ✅ passes

## CI Integration

Test scripts exit with code 0 on success, 1 on failure — compatible with CI pipelines.

Example GitHub Actions workflow:

```yaml
- name: Start PHP server
  run: php -S localhost:8081 -t backend/ &

- name: Run API tests
  run: php backend/tests/test_api.php
```
