<?php
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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Jakarta');

// ── 1. Manual override ──
$tutup_sementara = false;
if (file_exists(STATUS_FILE)) {
    $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
}

// ── 2. Operating hours check ──
$hari_inggris = date('l');
$jam_sekarang = date('H:i');
$jam_buka = loadJamOperasional();

$is_open = false;
$hari_ini = $jam_buka[$hari_inggris] ?? null;

if ($hari_ini && !$tutup_sementara) {
    $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
}

// ── 3. Schedule check ──
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

// ── 5. Next opening time ──
$next_buka = '';
$next_hari = '';

if (!$is_open && $hari_ini && !$tutup_sementara) {
    if ($jam_sekarang < $hari_ini['buka']) {
        // Opens later today
        $next_buka = $hari_ini['buka'];
        $next_hari = $hari_ini['indo'];
    }
}

if (empty($next_buka)) {
    // Search forward for next open day
    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $day_indo  = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $today_idx = array_search($hari_inggris, $day_names);

    for ($i = 1; $i <= 7; $i++) {
        $check_idx = ($today_idx + $i) % 7;
        $check_day = $day_names[$check_idx];
        $h = $jam_buka[$check_day] ?? null;

        if ($h && !empty($h['buka'])) {
            $next_buka = $h['buka'];
            $next_hari = $h['indo'];
            break;
        }
    }
}

// ── 6. Build response ──
$response = [
    'isOpen'               => $is_open,
    'isTemporarilyClosed'  => $tutup_sementara,
    'hasActiveSchedule'    => $has_schedule_now,
    'upcomingSchedule'     => $upcoming_schedule,
    'nextOpenDay'          => $next_hari,
    'nextOpenTime'         => $next_buka,
    'closeTime'            => ($hari_ini['tutup'] ?? ''),
    'currentDay'           => $hari_inggris,
    'currentDayIndo'       => $hari_ini['indo'] ?? '',
    'currentTime'          => $jam_sekarang,
    'hours'                => $jam_buka,
    'timestamp'            => date('c'),
];

echo json_encode($response, JSON_PRETTY_PRINT);
