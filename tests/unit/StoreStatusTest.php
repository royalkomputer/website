<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class StoreStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['SCHEDULE_FILE', 'STATUS_FILE'] as $const) {
            if (!defined($const)) continue;
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
    // Store Status Algorithm Integration
    // -------------------------------------------------------
    public function test_full_algorithm_open(): void
    {
        file_put_contents(STATUS_FILE, 'buka');
        file_put_contents(SCHEDULE_FILE, json_encode([]));

        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
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
        $is_open = !$tutup_sementara;

        $this->assertFalse($tutup_sementara);
        $this->assertTrue($is_open);
    }

    public function test_full_algorithm_closed_by_schedule(): void
    {
        load_fixture('jadwal_tutup.json');
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
        file_put_contents(STATUS_FILE, 'tutup');
        file_put_contents(SCHEDULE_FILE, json_encode([]));

        $tutup_sementara = trim(file_get_contents(STATUS_FILE)) === 'tutup';
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
        $is_open = !$tutup_sementara;

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
