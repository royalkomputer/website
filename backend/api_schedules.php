<?php
/**
 * backend/api_schedules.php — Public Closure Schedules Endpoint
 *
 * Returns all closure schedules. No authentication required.
 * Used by the Vite frontend to display upcoming schedule warnings.
 *
 * GET /api_schedules.php
 *
 * Response:
 * [
 *   {
 *     "id": "s_abc123",
 *     "start": "2026-06-20 08:00",
 *     "end": "2026-06-22 20:00",
 *     "note": "Libur Lebaran",
 *     "created_at": "2026-06-18 10:00"
 *   }
 * ]
 */

header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Jakarta');

$schedules = loadSchedules();

echo json_encode($schedules, JSON_PRETTY_PRINT);
