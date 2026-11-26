<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

use PHPUnit\Framework\TestCase;

class UserProfileManagementTest extends TestCase
{
    public function testCreatesAndUpdatesUserProfileWithAvatar(): void
    {
        $db = new DB(':memory:');

        try {
            $adminId = (int) $db->create('users', array(
                'username' => 'admin',
                'password_hash' => 'hash',
                'role' => 'admin',
            ));

            $created = auth_admin_save_user(array(
                'username' => 'ndominguez',
                'role' => 'personal',
                'active' => '1',
                'password' => 'secreto123',
                'first_name' => 'Noe',
                'last_name' => 'Dominguez Brito',
                'avatar_url' => 'https://cmsf.gob.ve/avatars/noe.png',
            ), $adminId, $db);

            self::assertTrue($created['ok']);
            $profile = $db->getFirstForConditions('profile', array('user_id' => $created['id']));
            self::assertIsArray($profile);
            self::assertSame('Noe', $profile['first_name']);
            self::assertSame('Dominguez Brito', $profile['last_name']);
            self::assertSame('https://cmsf.gob.ve/avatars/noe.png', $profile['avatar_url']);

            $updated = auth_admin_save_user(array(
                'user_id' => (string) $created['id'],
                'username' => 'ndominguez',
                'role' => 'personal',
                'active' => '1',
                'password' => '',
                'first_name' => 'Noe Miguel',
                'last_name' => 'Dominguez Brito',
                'avatar_url' => '',
            ), $adminId, $db);

            self::assertTrue($updated['ok']);
            $profile = $db->getFirstForConditions('profile', array('user_id' => $created['id']));
            self::assertSame('Noe Miguel', $profile['first_name']);
            self::assertSame('Dominguez Brito', $profile['last_name']);
            self::assertNull($profile['avatar_url']);
            self::assertSame(1, $db->countRows('profile'));
        } finally {
            $db->close();
        }
    }

    public function testRejectsIncompleteNamesAndUnsafeAvatarUrls(): void
    {
        $db = new DB(':memory:');

        try {
            $adminId = (int) $db->create('users', array(
                'username' => 'admin',
                'password_hash' => 'hash',
                'role' => 'admin',
            ));
            $base = array(
                'username' => 'perfil_invalido',
                'role' => 'personal',
                'active' => '1',
                'password' => 'secreto123',
                'first_name' => 'Noe',
                'last_name' => 'Dominguez Brito',
                'avatar_url' => '',
            );

            $incomplete = auth_admin_save_user(array_merge($base, array('last_name' => '')), $adminId, $db);
            self::assertFalse($incomplete['ok']);
            self::assertSame(1, $db->countRows('users'));

            foreach (array('javascript:alert(1)', 'data:text/html,test', 'not-a-url') as $invalidUrl) {
                $invalid = auth_admin_save_user(array_merge($base, array('avatar_url' => $invalidUrl)), $adminId, $db);
                self::assertFalse($invalid['ok']);
                self::assertStringContainsString('http o https', $invalid['message']);
                self::assertSame(1, $db->countRows('users'));
            }

            $avatarWithoutNames = auth_admin_save_user(array_merge($base, array(
                'first_name' => '',
                'last_name' => '',
                'avatar_url' => 'https://cmsf.gob.ve/avatar.png',
            )), $adminId, $db);
            self::assertFalse($avatarWithoutNames['ok']);
            self::assertSame(1, $db->countRows('users'));
        } finally {
            $db->close();
        }
    }

    public function testProfileFailureRollsBackNewUser(): void
    {
        $db = new DB(':memory:');

        try {
            $adminId = (int) $db->create('users', array(
                'username' => 'admin',
                'password_hash' => 'hash',
                'role' => 'admin',
            ));
            $db->exec("CREATE TRIGGER fail_profile_insert BEFORE INSERT ON profile BEGIN SELECT RAISE(ABORT, 'perfil bloqueado'); END");

            try {
                auth_admin_save_user(array(
                    'username' => 'perfil_rollback',
                    'role' => 'personal',
                    'active' => '1',
                    'password' => 'secreto123',
                    'first_name' => 'Noe',
                    'last_name' => 'Dominguez Brito',
                ), $adminId, $db);
                self::fail('El fallo de perfil debería interrumpir el alta.');
            } catch (Throwable $e) {
                self::assertStringContainsString('perfil bloqueado', $e->getMessage());
            }

            self::assertSame(1, $db->countRows('users'));
            self::assertSame(0, $db->countRows('profile'));
        } finally {
            $db->close();
        }
    }
}
