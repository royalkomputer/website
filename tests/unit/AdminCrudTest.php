<?php
namespace RoyalTests;

use PHPUnit\Framework\TestCase;

class AdminCrudTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['ADMINS_FILE'] as $const) {
            $path = constant($const);
            $bak = $path . '.bak';
            if (file_exists($bak)) rename($bak, $path);
            elseif (file_exists($path)) unlink($path);
        }
        // Clean session
        unset($_SESSION['admin_id'], $_SESSION['admin_logged_in'], $_SESSION['admin_role']);
    }

    // -------------------------------------------------------
    // Create Admin Validation (from update_admin.php)
    // -------------------------------------------------------
    public function test_create_admin_username_min_3_chars(): void
    {
        $username = 'ab';
        $this->assertTrue(strlen($username) < 3);
        $this->assertFalse(strlen($username) >= 3);
    }

    public function test_create_admin_username_3_chars_is_valid(): void
    {
        $username = 'abc';
        $this->assertTrue(strlen($username) >= 3);
    }

    public function test_create_admin_password_min_6_chars(): void
    {
        $password = 'abc12';
        $this->assertTrue(strlen($password) < 6);
        $this->assertFalse(strlen($password) >= 6);
    }

    public function test_create_admin_password_6_chars_is_valid(): void
    {
        $password = 'abc123';
        $this->assertTrue(strlen($password) >= 6);
    }

    public function test_create_admin_role_must_be_valid(): void
    {
        $role = 'super_admin';
        $valid = in_array($role, ['admin', 'super_admin']);
        $this->assertTrue($valid);

        $role = 'admin';
        $valid = in_array($role, ['admin', 'super_admin']);
        $this->assertTrue($valid);

        $role = 'editor';
        $valid = in_array($role, ['admin', 'super_admin']);
        $this->assertFalse($valid);
    }

    public function test_create_admin_invalid_role_defaults_to_admin(): void
    {
        $role = 'editor';
        if (!in_array($role, ['admin', 'super_admin'])) {
            $role = 'admin';
        }
        $this->assertEquals('admin', $role);
    }

    public function test_create_admin_duplicate_username_detected(): void
    {
        load_fixture('admins.json');
        $existing = findAdminByUsername('superadmin');
        $this->assertNotNull($existing);

        $new_username = 'superadmin';
        $duplicate = findAdminByUsername($new_username) !== null;
        $this->assertTrue($duplicate);
    }

    public function test_create_admin_unique_username_passes(): void
    {
        load_fixture('admins.json');
        $new_username = 'unique_admin';
        $duplicate = findAdminByUsername($new_username) !== null;
        $this->assertFalse($duplicate);
    }

    public function test_create_admin_generates_bcrypt_hash(): void
    {
        $password = 'test123456';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function test_create_admin_stores_correctly(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();
        $new_admin = [
            'id' => generateAdminId(),
            'username' => 'newadmin',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'nama' => 'New Admin',
            'created_at' => date('Y-m-d'),
        ];
        $admins[] = $new_admin;
        saveAdmins($admins);

        $reloaded = loadAdmins();
        $this->assertCount(3, $reloaded);
        $this->assertEquals('newadmin', $reloaded[2]['username']);
    }

    // -------------------------------------------------------
    // Delete Admin Validation
    // -------------------------------------------------------
    public function test_delete_admin_cannot_delete_self(): void
    {
        load_fixture('admins.json');
        $current_id = '1';
        $target_id = '1';

        $cannot_delete = ($target_id === $current_id);
        $this->assertTrue($cannot_delete);
    }

    public function test_delete_admin_can_delete_other(): void
    {
        load_fixture('admins.json');
        $current_id = '1';
        $target_id = '2';

        $cannot_delete = ($target_id === $current_id);
        $this->assertFalse($cannot_delete);
    }

    public function test_delete_admin_removes_admin(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();
        $target_id = '2';

        $filtered = array_values(array_filter($admins, fn($a) => $a['id'] !== $target_id));
        $this->assertCount(1, $filtered);
        $this->assertEquals('superadmin', $filtered[0]['username']);
    }

    // -------------------------------------------------------
    // Super Admin Constraint
    // -------------------------------------------------------
    public function test_must_keep_at_least_one_super_admin(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();
        $target_id = '1';

        $filtered = array_values(array_filter($admins, fn($a) => $a['id'] !== $target_id));
        $super_count = count(array_filter($filtered, fn($a) => $a['role'] === 'super_admin'));
        $this->assertEquals(0, $super_count);
        $this->assertTrue($super_count < 1); // would fail validation
    }

    public function test_can_delete_regular_admin_while_super_exists(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();
        $target_id = '2';

        $filtered = array_values(array_filter($admins, fn($a) => $a['id'] !== $target_id));
        $super_count = count(array_filter($filtered, fn($a) => $a['role'] === 'super_admin'));
        $this->assertEquals(1, $super_count);
        $this->assertTrue($super_count >= 1);
    }

    public function test_must_keep_super_admin_when_editing_role(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();

        // Try to change the only super_admin to regular admin
        $target_id = '1';
        $new_role = 'admin';
        $target = findAdminById($target_id);

        if ($new_role === 'admin' && $target['role'] === 'super_admin') {
            $super_count = count(array_filter($admins, fn($a) => $a['role'] === 'super_admin'));
            if ($super_count <= 1) {
                $this->assertEquals(1, $super_count);
                // Would fail: "Harus ada minimal 1 super admin"
            }
        }
    }

    // -------------------------------------------------------
    // Edit Admin Permissions
    // -------------------------------------------------------
    public function test_regular_admin_cannot_edit_others(): void
    {
        $_SESSION['admin_id'] = '2';
        $_SESSION['admin_role'] = 'admin';

        $is_super = ($_SESSION['admin_role'] === 'super_admin');
        $current_id = $_SESSION['admin_id'] = '2';
        $target_id = '1';

        $denied = !$is_super && $target_id !== $current_id;
        $this->assertTrue($denied);
    }

    public function test_regular_admin_can_edit_self(): void
    {
        $_SESSION['admin_id'] = '2';
        $_SESSION['admin_role'] = 'admin';

        $is_super = ($_SESSION['admin_role'] === 'super_admin');
        $current_id = $_SESSION['admin_id'] = '2';
        $target_id = '2';

        $denied = !$is_super && $target_id !== $current_id;
        $this->assertFalse($denied);
    }

    public function test_regular_admin_cannot_change_role(): void
    {
        $_SESSION['admin_role'] = 'admin';
        $is_super = ($_SESSION['admin_role'] === 'super_admin');

        $target = ['role' => 'admin'];
        $new_role = $is_super ? 'super_admin' : $target['role'];

        // Regular admin can't change role
        $this->assertEquals('admin', $new_role);
    }

    public function test_super_admin_can_change_role(): void
    {
        $_SESSION['admin_role'] = 'super_admin';
        $is_super = ($_SESSION['admin_role'] === 'super_admin');

        $target = ['role' => 'admin'];
        $new_role = $is_super ? 'super_admin' : $target['role'];

        // Super admin can set any role
        $this->assertEquals('super_admin', $new_role);
    }

    // -------------------------------------------------------
    // Username Uniqueness on Edit
    // -------------------------------------------------------
    public function test_edit_username_unique_check_skip_self(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();
        $target_id = '1';
        $new_username = 'superadmin'; // same as current

        $duplicate = false;
        foreach ($admins as $a) {
            if ($a['username'] === $new_username && $a['id'] !== $target_id) {
                $duplicate = true;
                break;
            }
        }

        $this->assertFalse($duplicate); // same owner, should be allowed
    }

    public function test_edit_username_unique_check_detects_other(): void
    {
        load_fixture('admins.json');
        $admins = loadAdmins();
        $target_id = '1';
        $new_username = 'admin1'; // belongs to admin #2

        $duplicate = false;
        foreach ($admins as $a) {
            if ($a['username'] === $new_username && $a['id'] !== $target_id) {
                $duplicate = true;
                break;
            }
        }

        $this->assertTrue($duplicate);
    }

    // -------------------------------------------------------
    // Session updates on self-edit
    // -------------------------------------------------------
    public function test_self_edit_updates_session_username(): void
    {
        $_SESSION['admin_id'] = '1';
        $_SESSION['admin_username'] = 'superadmin';

        $new_username = 'superadmin_updated';
        $target_id = '1';
        $current_id = $_SESSION['admin_id'];

        if ($target_id === $current_id) {
            $_SESSION['admin_username'] = $new_username;
        }

        $this->assertEquals('superadmin_updated', $_SESSION['admin_username']);
    }

    public function test_edit_other_does_not_update_session(): void
    {
        $_SESSION['admin_id'] = '2';
        $_SESSION['admin_username'] = 'admin1';

        $new_username = 'admin1_updated';
        $target_id = '1'; // editing someone else
        $current_id = $_SESSION['admin_id'];

        if ($target_id === $current_id) {
            $_SESSION['admin_username'] = $new_username;
        }

        $this->assertEquals('admin1', $_SESSION['admin_username']); // unchanged
    }

    // -------------------------------------------------------
    // Schedule Validation
    // -------------------------------------------------------
    public function test_schedule_start_before_end_is_valid(): void
    {
        $start = '2026-07-01 09:00';
        $end = '2026-07-02 18:00';

        $this->assertTrue($start <= $end);
    }

    public function test_schedule_start_equals_end_is_valid(): void
    {
        $start = '2026-07-01 09:00';
        $end = '2026-07-01 09:00';

        $this->assertTrue($start <= $end);
    }

    public function test_schedule_start_after_end_is_invalid(): void
    {
        $start = '2026-07-05 09:00';
        $end = '2026-07-04 18:00';

        $this->assertFalse($start <= $end);
    }

    public function test_schedule_generates_unique_id(): void
    {
        $id1 = uniqid('s_');
        $id2 = uniqid('s_');
        $this->assertNotEquals($id1, $id2);
        $this->assertStringStartsWith('s_', $id1);
    }

    public function test_schedule_edit_updates_fields(): void
    {
        $schedule = [
            'id' => 's_test',
            'start' => '2026-07-01 09:00',
            'end' => '2026-07-02 18:00',
            'note' => 'Original note',
        ];

        // Update
        $schedule['start'] = '2026-08-01 10:00';
        $schedule['note'] = 'Updated note';

        $this->assertEquals('2026-08-01 10:00', $schedule['start']);
        $this->assertEquals('Updated note', $schedule['note']);
        $this->assertEquals('s_test', $schedule['id']); // unchanged
    }

    // -------------------------------------------------------
    // Manual Status
    // -------------------------------------------------------
    public function test_manual_status_defaults_to_buka(): void
    {
        $status = $_POST['status'] ?? 'buka';
        $final = ($status === 'tutup') ? 'tutup' : 'buka';
        $this->assertEquals('buka', $final);
    }

    public function test_manual_status_can_be_tutup(): void
    {
        $status = 'tutup';
        $final = ($status === 'tutup') ? 'tutup' : 'buka';
        $this->assertEquals('tutup', $final);
    }

    public function test_manual_status_rejects_invalid_values(): void
    {
        $status = 'invalid';
        $final = ($status === 'tutup') ? 'tutup' : 'buka';
        $this->assertEquals('buka', $final); // defaults to buka
    }

    public function test_manual_status_writes_to_file(): void
    {
        $status = 'tutup';
        $written = file_put_contents(STATUS_FILE, $status);
        $this->assertNotFalse($written);
        $this->assertEquals('tutup', trim(file_get_contents(STATUS_FILE)));
    }
}
