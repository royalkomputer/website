#!/usr/bin/env bash
# =============================================================================
# backend/tests/test_api.sh — Royal Komputer API Test Suite (bash/curl)
# =============================================================================
# Usage:
#   1. Start the PHP server:  php -S localhost:8081 -t backend/
#   2. Run this script:       bash backend/tests/test_api.sh
#
# By default tests run against http://localhost:8081.
# Override with:  BASE_URL=http://my-server.com bash backend/tests/test_api.sh
#
# Requires: curl, python3, grep
#
# Exit codes:
#   0 = all tests passed
#   1 = one or more tests failed
# =============================================================================

set -uo pipefail

BASE_URL="${BASE_URL:-http://localhost:8081}"
PASS=0
FAIL=0
FAILURES=()

# ── Colors ──
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

# ── Test helpers ──

assert_status() {
    local desc="$1" method="$2" path="$3" expected="$4"
    local response
    response=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$BASE_URL$path" 2>/dev/null || true)
    if [ "$response" = "$expected" ]; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (expected $expected, got $response)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (HTTP $response, expected $expected)")
    fi
}

assert_json_has_key() {
    local desc="$1" method="$2" path="$3" key="$4"
    local body
    body=$(curl -s -X "$method" "$BASE_URL$path" 2>/dev/null || echo "{}")
    if echo "$body" | python3 -c "
import json,sys
d = json.load(sys.stdin)
assert '$key' in d, 'key $key missing'
" 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (key '$key' not found in JSON)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (key '$key' missing)")
    fi
}

assert_json_is_array() {
    local desc="$1" method="$2" path="$3"
    local body
    body=$(curl -s -X "$method" "$BASE_URL$path" 2>/dev/null || echo "[]")
    if echo "$body" | python3 -c "
import json,sys
d = json.load(sys.stdin)
assert isinstance(d, list), 'not a list'
" 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (response is not a JSON array)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (not a JSON array)")
    fi
}

assert_json_is_object() {
    local desc="$1" method="$2" path="$3"
    local body
    body=$(curl -s -X "$method" "$BASE_URL$path" 2>/dev/null || echo "{}")
    if echo "$body" | python3 -c "
import json,sys
d = json.load(sys.stdin)
assert isinstance(d, dict), 'not an object'
" 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (response is not a JSON object)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (not a JSON object)")
    fi
}

assert_json_bool_field() {
    local desc="$1" method="$2" path="$3" field="$4"
    local body
    body=$(curl -s -X "$method" "$BASE_URL$path" 2>/dev/null || echo "{}")
    if echo "$body" | python3 -c "
import json,sys
d = json.load(sys.stdin)
v = d.get('$field')
assert isinstance(v, bool), 'field $field is type ' + type(v).__name__ + ', expected bool'
" 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (field '$field' is not boolean)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (field '$field' not boolean)")
    fi
}

assert_json_str_field() {
    local desc="$1" method="$2" path="$3" field="$4"
    local body
    body=$(curl -s -X "$method" "$BASE_URL$path" 2>/dev/null || echo "{}")
    if echo "$body" | python3 -c "
import json,sys
d = json.load(sys.stdin)
v = d.get('$field')
assert isinstance(v, str), 'field $field is type ' + type(v).__name__ + ', expected str'
" 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (field '$field' is not a string)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (field '$field' not string)")
    fi
}

assert_contains() {
    local desc="$1" method="$2" path="$3" substring="$4"
    local body
    body=$(curl -s -X "$method" "$BASE_URL$path" 2>/dev/null || echo "")
    if echo "$body" | grep -q "$substring"; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (expected to contain '$substring')"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (missing '$substring')")
    fi
}

assert_cors_header() {
    local desc="$1" path="$2"
    local header
    header=$(curl -s -D - -o /dev/null -X OPTIONS "$BASE_URL$path" 2>/dev/null | grep -i 'access-control-allow-origin' | tr -d '\r' || echo "")
    if [ -n "$header" ]; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (no Access-Control-Allow-Origin header)"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (no CORS header)")
    fi
}

assert_redirect() {
    local desc="$1" path="$2" expected_location="$3"
    local location
    location=$(curl -s -o /dev/null -w "%{redirect_url}" "$BASE_URL$path" 2>/dev/null || echo "")
    if echo "$location" | grep -q "$expected_location"; then
        echo -e "  ${GREEN}✓${NC} $desc"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} $desc  (expected redirect to '$expected_location', got '$location')"
        FAIL=$((FAIL + 1))
        FAILURES+=("$desc (redirect to '$location')")
    fi
}

# ── Main test execution ──

echo ""
echo "========================================================"
echo "  Royal Komputer — Backend API Test Suite"
echo "  Target: $BASE_URL"
echo "========================================================"
echo ""

# 1. Connectivity
echo "[1/7] Connectivity"
assert_status "Server is reachable" GET "/index.php" 200

# 2. Health Check
echo ""
echo "[2/7] Health Check (index.php)"
assert_status "Health check returns 200" GET "/index.php" 200
assert_json_is_object "Response is a JSON object" GET "/index.php"
assert_json_has_key "Has 'status' key" GET "/index.php" status
assert_json_has_key "Has 'database' key" GET "/index.php" database
assert_json_has_key "Has 'service' key" GET "/index.php" service
assert_json_has_key "Has 'time' key" GET "/index.php" time
assert_json_str_field "'status' is a string" GET "/index.php" status
assert_json_str_field "'database' is a string" GET "/index.php" database

# 3. CORS
echo ""
echo "[3/7] CORS Headers"
assert_cors_header "api_produk.php returns CORS headers" "/api_produk.php"
assert_cors_header "api_status.php returns CORS headers" "/api_status.php"
assert_cors_header "api_schedules.php returns CORS headers" "/api_schedules.php"
# CORS OPTIONS preflight should return 204
assert_status "api_produk.php OPTIONS returns 204" OPTIONS "/api_produk.php" 204
assert_status "api_status.php OPTIONS returns 204" OPTIONS "/api_status.php" 204
assert_status "api_schedules.php OPTIONS returns 204" OPTIONS "/api_schedules.php" 204

# 4. Store Status
echo ""
echo "[4/7] Store Status (api_status.php)"
assert_status "Store status returns 200" GET "/api_status.php" 200
assert_json_is_object "Response is a JSON object" GET "/api_status.php"
assert_json_has_key "Has 'isOpen' field" GET "/api_status.php" isOpen
assert_json_has_key "Has 'isTemporarilyClosed' field" GET "/api_status.php" isTemporarilyClosed
assert_json_has_key "Has 'closeTime' field" GET "/api_status.php" closeTime
assert_json_has_key "Has 'hours' field" GET "/api_status.php" hours
assert_json_has_key "Has 'timestamp' field" GET "/api_status.php" timestamp
assert_json_bool_field "isOpen is boolean" GET "/api_status.php" isOpen
assert_json_bool_field "isTemporarilyClosed is boolean" GET "/api_status.php" isTemporarilyClosed
assert_contains "Hours includes Monday" GET "/api_status.php" "Monday"

# 5. Schedules
echo ""
echo "[5/7] Schedules (api_schedules.php)"
assert_status "Schedules returns 200" GET "/api_schedules.php" 200
assert_json_is_array "Response is a JSON array" GET "/api_schedules.php"

# 6. Auth Protection
echo ""
echo "[6/7] Auth Protection"
assert_status "admin.php redirects to login (302)" GET "/admin.php" 302
assert_redirect "admin.php redirects to login.php" "/admin.php" "login.php"

# Auth-protected POST endpoints should return JSON error (not logged in)
assert_contains "update_produk.php rejects unauthenticated" POST "/update_produk.php" "Akses ditolak"
assert_contains "update_admin.php rejects unauthenticated" POST "/update_admin.php" "Akses ditolak"
assert_contains "update_jam.php rejects unauthenticated" POST "/update_jam.php" "Akses ditolak"
assert_contains "api_manage_photos.php rejects unauthenticated" POST "/api_manage_photos.php" "Akses ditolak"

# 7. Content Types
echo ""
echo "[7/7] Content Type Headers"
assert_contains "api_produk.php returns JSON" GET "/api_produk.php" "{"
assert_contains "api_status.php returns JSON" GET "/api_status.php" "{"
assert_contains "api_schedules.php returns JSON" GET "/api_schedules.php" "["
assert_contains "login.php returns HTML" GET "/login.php" "<!DOCTYPE"
assert_contains "index.php returns JSON" GET "/index.php" "{"

# ── Summary ──
echo ""
echo "========================================================"
if [ "$FAIL" -eq 0 ]; then
    echo -e "  ${GREEN}ALL $PASS TESTS PASSED${NC}"
else
    echo -e "  ${RED}$FAIL TESTS FAILED${NC}, ${GREEN}$PASS PASSED${NC}"
    echo ""
    echo "  Failed tests:"
    for f in "${FAILURES[@]}"; do
        echo "    - $f"
    done
fi
echo "========================================================"
echo ""
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
