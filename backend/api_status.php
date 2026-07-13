<?php
error_reporting(E_ERROR | E_PARSE);

/**
 * backend/api_status.php — Public Store Status Endpoint
 *
 * Returns the current store status: open/closed, operating hours,
 * active schedules, and next opening time. No authentication required.
 *
 * GET /api_status.php
 *
 * Response:
 * {
 *   "isOpen": bool,
 *   "isTemporarilyClosed": bool,
 *   "hasActiveSchedule": bool,
 *   "upcomingSchedule": { "start": "...", "end": "...", "note": "..." } | null,
 *   "nextOpenDay": "Senin",
 *   "nextOpenTime": "09:00",
 *   "closeTime": "21:00",
 *   "currentDay": "Monday",
 *   "currentDayIndo": "Senin",
 *   "currentTime": "14:30",
 *   "hours": { ... },       // Full operating hours object
 *   "timestamp": "2026-06-18T14:30:00+07:00"
 * }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/cors.php';
handleCORS();

require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Jakarta');

// ── 1. Manual override ──
$tutup_sementara = loadStatus() === 'tutup';

// ── 2. Schedule check ──
$hari_inggris = date('l');
$jam_sekarang = date('H:i');
$schedules = loadSchedules();
$now_dt = date('Y-m-d H:i');
$has_schedule_now = false;

foreach ($schedules as $s) {
    if (!empty($s['start']) && !empty($s['end'])) {
        if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
            $has_schedule_now = true;
            break;
        }
    }
}

if ($has_schedule_now) {
    $tutup_sementara = true;
    $is_open = false;
}

// ── 3. Store is open unless manually/temporarily closed ──
$is_open = !$tutup_sementara;
$hari_ini = null;

// ── 4. Upcoming schedule ──
$upcoming_schedule = null;
$future_schedules = array_filter($schedules, function ($s) use ($now_dt) {
    return !empty($s['end']) && $s['end'] >= $now_dt;
});
usort($future_schedules, function ($a, $b) {
    return strcmp($a['start'], $b['start']);
});
if (!empty($future_schedules)) {
    $upcoming_schedule = $future_schedules[0];
}

// ── Tagline toko ──
$tagline = loadTagline();

// ── Heading toko ──
$heading = loadHeading();

// ── 5. Build response ──
$response = [
    'isOpen'               => $is_open,
    'isTemporarilyClosed'  => $tutup_sementara,
    'hasActiveSchedule'    => $has_schedule_now,
    'upcomingSchedule'     => $upcoming_schedule,
    'nextOpenDay'          => '',
    'nextOpenTime'         => '',
    'closeTime'            => '',
    'currentDay'           => $hari_inggris,
    'currentDayIndo'       => '',
    'currentTime'          => $jam_sekarang,
    'tagline'              => $tagline,
    'heading'              => $heading,
    'timestamp'            => date('c'),
];

echo json_encode($response, JSON_PRETTY_PRINT);
