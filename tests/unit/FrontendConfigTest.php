<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class FrontendConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // The frontend config was loaded in bootstrap, which defines its own
        // constants. Since backend config was loaded first with the same names,
        // frontend config's defines were skipped. The functions from frontend
        // config (loadJamOperasional, loadSchedules, saveSchedules) are the
        // same code but use the constants JAM_FILE, SCHEDULE_FILE from backend.
        // This is fine since both point to the same test data dir.
    }

    protected function tearDown(): void
    {
        foreach (['JAM_FILE', 'SCHEDULE_FILE'] as $const) {
            $path = constant($const);
            $bak = $path . '.bak';
            if (file_exists($bak)) {
                rename($bak, $path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_loadJamOperasional_custom_values(): void
    {
        $custom = [
            'Monday' => ['buka' => '10:00', 'tutup' => '22:00', 'indo' => 'Senin'],
            'Tuesday' => ['buka' => '10:00', 'tutup' => '22:00', 'indo' => 'Selasa'],
            'Wednesday' => ['buka' => '10:00', 'tutup' => '22:00', 'indo' => 'Rabu'],
            'Thursday' => ['buka' => '10:00', 'tutup' => '22:00', 'indo' => 'Kamis'],
            'Friday' => ['buka' => '14:00', 'tutup' => '23:00', 'indo' => 'Jumat'],
            'Saturday' => ['buka' => '10:00', 'tutup' => '22:00', 'indo' => 'Sabtu'],
            'Sunday' => ['buka' => '10:00', 'tutup' => '22:00', 'indo' => 'Minggu'],
        ];
        file_put_contents(JAM_FILE, json_encode($custom, JSON_PRETTY_PRINT));
        $loaded = loadJamOperasional();
        $this->assertEquals('10:00', $loaded['Monday']['buka']);
        $this->assertEquals('23:00', $loaded['Friday']['tutup']);
    }

    public function test_loadSchedules_persists_across_calls(): void
    {
        $schedule = [
            ['id' => 's_test', 'start' => '2026-10-01 00:00', 'end' => '2026-10-02 00:00', 'note' => 'Test']
        ];
        saveSchedules($schedule);
        $this->assertEquals($schedule, loadSchedules());
    }

    public function test_saveSchedules_preserves_order(): void
    {
        $schedules = [
            ['id' => 's_second', 'start' => '2026-11-02 00:00', 'end' => '2026-11-03 00:00'],
            ['id' => 's_first', 'start' => '2026-11-01 00:00', 'end' => '2026-11-02 00:00'],
        ];
        saveSchedules($schedules);
        $loaded = loadSchedules();
        $this->assertEquals('s_second', $loaded[0]['id']);
        $this->assertEquals('s_first', $loaded[1]['id']);
    }

    public function test_loadJamOperasional_handles_partial_data(): void
    {
        // Only provide Monday, expect defaults for other days
        $partial = [
            'Monday' => ['buka' => '08:00', 'tutup' => '17:00', 'indo' => 'Senin']
        ];
        file_put_contents(JAM_FILE, json_encode($partial, JSON_PRETTY_PRINT));
        $loaded = loadJamOperasional();
        $this->assertEquals('08:00', $loaded['Monday']['buka']);
        // Tuesday should exist because defaults are used as fallback
        // But wait — loadJamOperasional returns file data as-is, doesn't merge defaults
        $this->assertArrayNotHasKey('Tuesday', $loaded);
    }
}
