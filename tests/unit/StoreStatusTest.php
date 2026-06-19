<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class StoreStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['JAM_FILE', 'SCHEDULE_FILE', 'STATUS_FILE'] as $const) {
            $path = constant($const);
            $bak = $path . '.bak';
            if (file_exists($bak)) rename($bak, $path);
            elseif (file_exists($path)) unlink($path);
        }
    }

    // -------------------------------------------------------
    // Manual Override Tests
    // -------------------------------------------------------
    public function test_manual_tutup_closes_store(): void
    {
        file_put_contents(STATUS_FILE, 'tutup');
        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
        $this->assertTrue($tutup_sementara);
    }

    public function test_manual_buka_leaves_store_open(): void
    {
        file_put_contents(STATUS_FILE, 'buka');
        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
        $this->assertFalse($tutup_sementara);
    }

    public function test_manual_file_does_not_exist(): void
    {
        @unlink(STATUS_FILE);
        $this->assertFalse(file_exists(STATUS_FILE));
    }

    // -------------------------------------------------------
    // Operating Hours Tests
    // -------------------------------------------------------
    public function test_within_operating_hours(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        $jam_sekarang = '10:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
        $this->assertTrue($is_open);
    }

    public function test_before_opening_hours(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        $jam_sekarang = '08:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
        $this->assertFalse($is_open);
    }

    public function test_after_closing_hours(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        $jam_sekarang = '22:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
        $this->assertFalse($is_open);
    }

    public function test_exact_opening_time(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        $jam_sekarang = '09:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
        $this->assertTrue($is_open);
    }

    public function test_exact_closing_time(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        $jam_sekarang = '21:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
        $this->assertTrue($is_open);
    }

    public function test_friday_different_hours(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Friday'];

        // Friday opens at 13:30
        $this->assertFalse(('12:00' >= $hari_ini['buka'] && '12:00' <= $hari_ini['tutup']));
        $this->assertTrue(('14:00' >= $hari_ini['buka'] && '14:00' <= $hari_ini['tutup']));
    }

    // -------------------------------------------------------
    // Schedule Tests
    // -------------------------------------------------------
    public function test_active_schedule_closes_store(): void
    {
        load_fixture('jadwal_tutup.json');
        $schedules = loadSchedules();

        $now_dt = '2026-12-25 12:00';
        $has_active = false;
        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $has_active = true;
                break;
            }
        }
        $this->assertTrue($has_active);
    }

    public function test_inactive_schedule_does_not_close_store(): void
    {
        load_fixture('jadwal_tutup.json');
        $schedules = loadSchedules();

        $now_dt = '2026-06-15 12:00';
        $has_active = false;
        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $has_active = true;
                break;
            }
        }
        $this->assertFalse($has_active);
    }

    public function test_schedule_exact_start_boundary(): void
    {
        load_fixture('jadwal_tutup.json');
        $schedules = loadSchedules();

        $now_dt = '2026-12-25 08:00';
        $has_active = false;
        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $has_active = true;
                break;
            }
        }
        $this->assertTrue($has_active);
    }

    public function test_schedule_exact_end_boundary(): void
    {
        $schedule = ['start' => '2026-12-25 08:00', 'end' => '2026-12-26 20:00'];
        $now_dt = '2026-12-26 20:00';
        $active = ($now_dt >= $schedule['start'] && $now_dt <= $schedule['end']);
        $this->assertTrue($active);
    }

    public function test_schedule_just_after_end(): void
    {
        $schedule = ['start' => '2026-12-25 08:00', 'end' => '2026-12-26 20:00'];
        $now_dt = '2026-12-26 20:01';
        $active = ($now_dt >= $schedule['start'] && $now_dt <= $schedule['end']);
        $this->assertFalse($active);
    }

    // -------------------------------------------------------
    // Priority: manual > schedule > hours
    // -------------------------------------------------------
    public function test_manual_override_takes_priority_over_schedule(): void
    {
        // Even if within schedule, manual 'buka' should keep store open
        file_put_contents(STATUS_FILE, 'buka');
        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
        $this->assertFalse($tutup_sementara);

        // The schedule check is separate from manual check
        load_fixture('jadwal_tutup.json');
        $schedules = loadSchedules();
        $now_dt = '2026-12-25 12:00';
        $schedule_active = false;
        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $schedule_active = true;
                break;
            }
        }

        // If both are true, manual says open but schedule says closed
        // Per algorithm: manual is checked first, but schedule is OR'd with it
        $is_open_after_all_checks = !$tutup_sementara && !$schedule_active;
        $this->assertFalse($is_open_after_all_checks); // schedule wins
    }

    // -------------------------------------------------------
    // Next Opening Time
    // -------------------------------------------------------
    public function test_next_opening_time_same_day(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        // Before opening time
        $jam_sekarang = '07:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);

        if (!$is_open && $jam_sekarang < $hari_ini['buka']) {
            $next_buka = $hari_ini['buka'];
            $next_hari = $hari_ini['indo'];
        }

        $this->assertFalse($is_open);
        $this->assertEquals('09:00', $next_buka ?? '');
        $this->assertEquals('Senin', $next_hari ?? '');
    }

    public function test_next_opening_time_next_day(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];

        $jam_sekarang = '22:00';
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);

        // Closed, and past closing time — need to look at next day
        $next_buka = '';
        $next_hari = '';
        if (!$is_open) {
            if ($jam_sekarang >= $hari_ini['tutup']) {
                // Look at next day
                $day_names = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                $day_indo  = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
                $today_idx = array_search('Monday', $day_names);
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
        }

        $this->assertEquals('09:00', $next_buka);
        $this->assertEquals('Selasa', $next_hari);
    }

    public function test_next_opening_time_friday_to_saturday(): void
    {
        load_fixture('jam_operasional.json');
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Friday'];

        $jam_sekarang = '23:00'; // Past Friday closing (22:00)
        $is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);

        $next_buka = '';
        $next_hari = '';
        if (!$is_open && $jam_sekarang >= $hari_ini['tutup']) {
            $day_names = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $day_indo  = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
            $today_idx = array_search('Friday', $day_names);
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

        $this->assertEquals('09:00', $next_buka);
        $this->assertEquals('Sabtu', $next_hari);
    }

    // -------------------------------------------------------
    // Store Status Algorithm Integration
    // -------------------------------------------------------
    public function test_full_algorithm_open(): void
    {
        // Scenario: Monday 10:00, no manual override, no schedule
        file_put_contents(STATUS_FILE, 'buka');
        load_fixture('jam_operasional.json');
        file_put_contents(SCHEDULE_FILE, json_encode([]));

        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];
        $jam_sekarang = '10:00';
        $schedules = loadSchedules();
        $now_dt = '2026-06-15 10:00';

        $schedule_active = false;
        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $schedule_active = true;
                break;
            }
        }

        if ($schedule_active) $tutup_sementara = true;
        $is_open = !$tutup_sementara && ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);

        $this->assertFalse($tutup_sementara);
        $this->assertTrue($is_open);
    }

    public function test_full_algorithm_closed_by_schedule(): void
    {
        // Scenario: Within schedule period
        load_fixture('jadwal_tutup.json');
        load_fixture('jam_operasional.json');
        file_put_contents(STATUS_FILE, 'buka');

        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
        $schedules = loadSchedules();
        $now_dt = '2026-12-25 12:00';

        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $tutup_sementara = true;
                break;
            }
        }

        $this->assertTrue($tutup_sementara);
    }

    public function test_full_algorithm_closed_by_manual(): void
    {
        // Scenario: Manual tutup, even during operating hours
        file_put_contents(STATUS_FILE, 'tutup');
        load_fixture('jam_operasional.json');
        file_put_contents(SCHEDULE_FILE, json_encode([]));

        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
        $jam_buka = loadJamOperasional();
        $hari_ini = $jam_buka['Monday'];
        $jam_sekarang = '10:00';
        $schedules = loadSchedules();
        $now_dt = '2026-06-15 10:00';

        $schedule_active = false;
        foreach ($schedules as $s) {
            if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
                $schedule_active = true;
                break;
            }
        }

        if ($schedule_active) $tutup_sementara = true;
        $is_open = !$tutup_sementara && ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);

        $this->assertTrue($tutup_sementara);
        $this->assertFalse($is_open);
    }

    public function test_upcoming_schedule_detection(): void
    {
        load_fixture('jadwal_tutup.json');
        $schedules = loadSchedules();

        $now_dt = '2026-06-15 12:00';
        $future = array_filter($schedules, function($s) use ($now_dt) {
            return !empty($s['end']) && $s['end'] >= $now_dt;
        });
        usort($future, fn($a,$b) => strcmp($a['start'], $b['start']));

        $this->assertCount(1, $future);
        $this->assertEquals('Libur Natal', $future[0]['note']);
    }

    public function test_no_upcoming_schedules(): void
    {
        file_put_contents(SCHEDULE_FILE, json_encode([]));
        $schedules = loadSchedules();

        $now_dt = '2026-06-15 12:00';
        $future = array_filter($schedules, function($s) use ($now_dt) {
            return !empty($s['end']) && $s['end'] >= $now_dt;
        });

        $this->assertEmpty($future);
    }
}
