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
        // config (loadSchedules, saveSchedules) are the
        // same code but use the constants SCHEDULE_FILE from backend.
        // This is fine since both point to the same test data dir.
    }

    protected function tearDown(): void
    {
        foreach (['SCHEDULE_FILE'] as $const) {
            if (!defined($const)) continue;
            $path = constant($const);
            $bak = $path . '.bak';
            if (file_exists($bak)) {
                rename($bak, $path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_loadSchedules_returns_data_from_file(): void
    {
        $schedules = loadSchedules();
        $this->assertIsArray($schedules);
        $this->assertNotEmpty($schedules);
        $this->assertEquals('s_abc123', $schedules[0]['id']);
    }

    public function test_loadSchedules_empty_when_file_missing(): void
    {
        @unlink(SCHEDULE_FILE);
        $schedules = loadSchedules();
        $this->assertIsArray($schedules);
        $this->assertEmpty($schedules);
        $this->assertFileExists(SCHEDULE_FILE);
    }

    public function test_saveSchedules_roundtrip(): void
    {
        $data = [
            ['id' => 's_frontend_test', 'start' => '2026-08-01 09:00', 'end' => '2026-08-02 18:00', 'note' => 'Frontend Test']
        ];
        $this->assertTrue(saveSchedules($data));
        $loaded = loadSchedules();
        $this->assertEquals($data, $loaded);
    }
}
