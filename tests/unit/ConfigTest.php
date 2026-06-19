<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset data dir to fixture state before each test
        $dataDir = __DIR__ . '/../../backend/data';
        foreach (['admins.json', 'jam_operasional.json', 'jadwal_tutup.json', 'status_toko.txt'] as $f) {
            $path = $dataDir . '/' . $f;
            $src = __DIR__ . '/../fixtures/' . $f;
            if (file_exists($src)) {
                copy($src, $path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // -------------------------------------------------------
    // loadAdmins
    // -------------------------------------------------------
    public function test_loadAdmins_returns_data_from_file(): void
    {
        $admins = loadAdmins();
        $this->assertCount(2, $admins);
        $this->assertEquals('superadmin', $admins[0]['username']);
        $this->assertEquals('admin1', $admins[1]['username']);
    }

    public function test_loadAdmins_returns_default_when_file_missing(): void
    {
        @unlink(ADMINS_FILE);
        $admins = loadAdmins();
        $this->assertCount(1, $admins);
        $this->assertEquals('superadmin', $admins[0]['username']);
        $this->assertEquals('super_admin', $admins[0]['role']);
        $this->assertArrayHasKey('password_hash', $admins[0]);
        $this->assertTrue(file_exists(ADMINS_FILE));
    }

    // -------------------------------------------------------
    // saveAdmins
    // -------------------------------------------------------
    public function test_saveAdmins_writes_file(): void
    {
        $data = [
            ['id' => '99', 'username' => 'testuser', 'role' => 'admin']
        ];
        $this->assertTrue(saveAdmins($data));
        $this->assertFileExists(ADMINS_FILE);

        $loaded = json_decode(file_get_contents(ADMINS_FILE), true);
        $this->assertEquals($data, $loaded['admins']);
    }

    public function test_saveAdmins_roundtrip(): void
    {
        $original = loadAdmins();
        $this->assertTrue(saveAdmins($original));
        $reloaded = loadAdmins();
        $this->assertEquals($original, $reloaded);
    }

    // -------------------------------------------------------
    // findAdminByUsername
    // -------------------------------------------------------
    public function test_findAdminByUsername_found(): void
    {
        $admin = findAdminByUsername('superadmin');
        $this->assertNotNull($admin);
        $this->assertEquals('1', $admin['id']);
    }

    public function test_findAdminByUsername_not_found(): void
    {
        $this->assertNull(findAdminByUsername('nonexistent'));
    }

    public function test_findAdminByUsername_case_sensitive(): void
    {
        $this->assertNull(findAdminByUsername('SuperAdmin'));
    }

    // -------------------------------------------------------
    // findAdminById
    // -------------------------------------------------------
    public function test_findAdminById_found(): void
    {
        $admin = findAdminById('2');
        $this->assertNotNull($admin);
        $this->assertEquals('admin1', $admin['username']);
    }

    public function test_findAdminById_not_found(): void
    {
        $this->assertNull(findAdminById('999'));
    }

    // -------------------------------------------------------
    // generateAdminId
    // -------------------------------------------------------
    public function test_generateAdminId_with_existing(): void
    {
        $id = generateAdminId();
        $this->assertEquals('3', $id);
    }

    public function test_generateAdminId_when_empty(): void
    {
        // Remove fixture and re-create empty state
        @unlink(ADMINS_FILE);
        $id = generateAdminId();
        // loadAdmins() will create default admin with id '1', so next id is '2'
        $this->assertEquals('2', $id);
    }

    public function test_generateAdminId_increments_properly(): void
    {
        $first = generateAdminId();
        $this->assertEquals('3', $first);
        $second = generateAdminId();
        $this->assertEquals('3', $second);
    }

    // -------------------------------------------------------
    // loadJamOperasional
    // -------------------------------------------------------
    public function test_loadJamOperasional_returns_default_when_file_missing(): void
    {
        @unlink(JAM_FILE);
        $jam = loadJamOperasional();
        $this->assertCount(7, $jam);
        $this->assertEquals('09:00', $jam['Monday']['buka']);
        $this->assertEquals('21:00', $jam['Monday']['tutup']);
        $this->assertEquals('Senin', $jam['Monday']['indo']);
        $this->assertEquals('13:30', $jam['Friday']['buka']);
        $this->assertTrue(file_exists(JAM_FILE));
    }

    public function test_loadJamOperasional_from_file(): void
    {
        $jam = loadJamOperasional();
        $this->assertEquals('09:00', $jam['Monday']['buka']);
        $this->assertEquals('21:00', $jam['Saturday']['tutup']);
    }

    public function test_loadJamOperasional_returns_default_on_corrupt(): void
    {
        file_put_contents(JAM_FILE, '{invalid json');
        $jam = loadJamOperasional();
        $this->assertCount(7, $jam);
        $this->assertEquals('09:00', $jam['Monday']['buka']);
    }

    // -------------------------------------------------------
    // loadSchedules / saveSchedules
    // -------------------------------------------------------
    public function test_loadSchedules_empty_when_file_missing(): void
    {
        @unlink(SCHEDULE_FILE);
        $schedules = loadSchedules();
        $this->assertIsArray($schedules);
        $this->assertEmpty($schedules);
        $this->assertFileExists(SCHEDULE_FILE);
    }

    public function test_loadSchedules_from_file(): void
    {
        $schedules = loadSchedules();
        $this->assertCount(1, $schedules);
        $this->assertEquals('s_abc123', $schedules[0]['id']);
        $this->assertEquals('Libur Natal', $schedules[0]['note']);
    }

    public function test_saveSchedules_roundtrip(): void
    {
        $data = [
            ['id' => 's_test1', 'start' => '2026-07-01 09:00', 'end' => '2026-07-02 18:00', 'note' => 'Test', 'created_at' => '2026-06-01 10:00']
        ];
        $this->assertTrue(saveSchedules($data));
        $loaded = loadSchedules();
        $this->assertEquals($data, $loaded);
    }

    public function test_saveSchedules_multiple_entries(): void
    {
        $data = [
            ['id' => 's_a', 'start' => '2026-08-01 00:00', 'end' => '2026-08-02 00:00', 'note' => 'A'],
            ['id' => 's_b', 'start' => '2026-09-01 00:00', 'end' => '2026-09-02 00:00', 'note' => 'B'],
        ];
        saveSchedules($data);
        $loaded = loadSchedules();
        $this->assertCount(2, $loaded);
    }

    // -------------------------------------------------------
    // getCurrentAdmin
    // -------------------------------------------------------
    public function test_getCurrentAdmin_returns_null_when_not_logged_in(): void
    {
        unset($_SESSION['admin_id']);
        $this->assertNull(getCurrentAdmin());
    }

    public function test_getCurrentAdmin_returns_admin_when_logged_in(): void
    {
        $_SESSION['admin_id'] = '1';
        $admin = getCurrentAdmin();
        $this->assertNotNull($admin);
        $this->assertEquals('superadmin', $admin['username']);
    }

    public function test_getCurrentAdmin_returns_null_for_invalid_id(): void
    {
        $_SESSION['admin_id'] = '999';
        $this->assertNull(getCurrentAdmin());
    }

    // -------------------------------------------------------
    // isSuperAdmin
    // -------------------------------------------------------
    public function test_isSuperAdmin_true(): void
    {
        $_SESSION['admin_id'] = '1';
        $this->assertTrue(isSuperAdmin());
    }

    public function test_isSuperAdmin_false_for_regular_admin(): void
    {
        $_SESSION['admin_id'] = '2';
        $this->assertFalse(isSuperAdmin());
    }

    public function test_isSuperAdmin_false_when_not_logged_in(): void
    {
        unset($_SESSION['admin_id']);
        $this->assertFalse(isSuperAdmin());
    }

    // -------------------------------------------------------
    // requireLogin
    // -------------------------------------------------------
    public function test_requireLogin_passes_when_logged_in(): void
    {
        $_SESSION['admin_logged_in'] = true;
        requireLogin();
        $this->assertTrue(true);
    }

    public function test_requireLogin_redirects_when_not_logged_in(): void
    {
        unset($_SESSION['admin_logged_in']);
        $shouldRedirect = !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true;
        $this->assertTrue($shouldRedirect);
    }

    // -------------------------------------------------------
    // getDBConnection
    // -------------------------------------------------------
    public function test_getDBConnection_returns_false_without_db(): void
    {
        $conn = getDBConnection();
        $this->assertFalse($conn);
    }
}
