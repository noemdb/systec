<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

use PHPUnit\Framework\TestCase;

class BackupAndUsersTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_ops_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0750, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testTechnicianCatalogReflectsActiveAndDeactivatedTechnicianUsers(): void
    {
        $db = new DB(':memory:');

        try {
            // Estado inicial: incluye los técnicos históricos base
            $initial = $db->getListMaintenanceTechnician();
            self::assertArrayHasKey('jmedina', $initial);
            self::assertArrayHasKey('ndominguez', $initial);

            // ARCH-02: Al crear un nuevo técnico activo, aparece inmediatamente en el selector
            $adminId = $db->create('users', array(
                'username' => 'admin01',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'active' => 1,
            ));

            $saveRes = auth_admin_save_user(array(
                'username' => 'carlos_tec',
                'role' => 'tecnico',
                'password' => 'secreto123',
                'active' => '1',
            ), $adminId, $db);
            self::assertTrue($saveRes['ok']);

            $withNewTech = $db->getListMaintenanceTechnician();
            self::assertArrayHasKey('carlos_tec', $withNewTech);

            // Al desactivar al técnico, desaparece inmediatamente del catálogo
            $disableRes = auth_admin_save_user(array(
                'user_id' => (string) $saveRes['id'],
                'role' => 'tecnico',
                'active' => '0',
                'password' => '',
            ), $adminId, $db);
            self::assertTrue($disableRes['ok']);

            $afterDisable = $db->getListMaintenanceTechnician();
            self::assertArrayNotHasKey('carlos_tec', $afterDisable);
        } finally {
            $db->close();
        }
    }

    public function testUserPasswordChangeAndAdminSelfProtectionRules(): void
    {
        $db = new DB(':memory:');

        try {
            $adminId = $db->create('users', array(
                'username' => 'admin_principal',
                'password_hash' => password_hash('claveActual123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'active' => 1,
            ));

            // 1. Contraseña actual incorrecta se rechaza
            $wrongCurrent = auth_change_password($adminId, 'incorrecta', 'nuevaClave123', 'nuevaClave123', $db);
            self::assertFalse($wrongCurrent['ok']);

            // 2. Confirmación desigual o longitud < 6 se rechaza
            $shortPwd = auth_change_password($adminId, 'claveActual123', '12345', '12345', $db);
            self::assertFalse($shortPwd['ok']);
            $mismatchPwd = auth_change_password($adminId, 'claveActual123', 'nuevaClave123', 'otraClave123', $db);
            self::assertFalse($mismatchPwd['ok']);

            // 3. Cambio válido actualiza el hash
            $changed = auth_change_password($adminId, 'claveActual123', 'nuevaClave456', 'nuevaClave456', $db);
            self::assertTrue($changed['ok']);
            $updatedRow = $db->getFirstForId('users', $adminId);
            self::assertTrue(password_verify('nuevaClave456', (string) $updatedRow['password_hash']));

            // 4. Un administrador no puede desactivar ni degradar su propia cuenta en sesión
            $selfDeactivate = auth_admin_save_user(array(
                'user_id' => (string) $adminId,
                'role' => 'admin',
                'active' => '0',
            ), $adminId, $db);
            self::assertFalse($selfDeactivate['ok']);

            $selfDemote = auth_admin_save_user(array(
                'user_id' => (string) $adminId,
                'role' => 'consulta',
                'active' => '1',
            ), $adminId, $db);
            self::assertFalse($selfDemote['ok']);
        } finally {
            $db->close();
        }
    }

    public function testAutomatedBackupScriptVerifiesIntegrityEncryptsAndRotates(): void
    {
        $storageDir = $this->tempDir . '/storage';
        $backupDest = $this->tempDir . '/backups';
        $logFile = $this->tempDir . '/logs/backup.log';
        $keyFile = $this->tempDir . '/backup.key';
        mkdir($storageDir, 0750, true);
        file_put_contents($keyFile, "clave-secreta-de-respaldo-2026\n");
        chmod($keyFile, 0600);

        $dbFile = $storageDir . '/db.db';
        $db = new DB($dbFile);
        $db->create('properties', array('ident' => '0009', 'description' => 'Router central', 'status' => 'BUENO'));
        $db->close();
        file_put_contents($storageDir . '/properties.csv', "2;0101;001;0009;Router\n");

        $script = realpath(__DIR__ . '/../../bin/backup.sh');
        self::assertNotFalse($script);

        // Ejecutar 3 respaldos con límite de retención diaria = 2 y cifrado obligatorio
        $stamps = array('2026-11-16_020000', '2026-11-17_020000', '2026-11-18_020000');
        foreach ($stamps as $stamp) {
            $env = array_merge($_ENV, array(
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
                'SYSTEC_STORAGE' => $storageDir,
                'SYSTEC_DB' => $dbFile,
                'SYSTEC_BACKUP_DEST' => $backupDest,
                'SYSTEC_BACKUP_LOG' => $logFile,
                'SYSTEC_BACKUP_KEY_FILE' => $keyFile,
                'SYSTEC_BACKUP_NOW' => $stamp,
                'SYSTEC_BACKUP_KEEP_DAILY' => '2',
            ));
            $descriptors = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'),
            );
            $proc = proc_open(array($script, '--require-encryption'), $descriptors, $pipes, null, $env);
            self::assertIsResource($proc);
            fclose($pipes[0]);
            $stdout = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);

            self::assertSame(0, $exitCode, 'Fallo en backup.sh: ' . $stderr . ' ' . $stdout);
        }

        // Verificar rotación diaria (quedan 2 de los 3 ejecutados) y promoción semanal/mensual
        $dailyFiles = glob($backupDest . '/daily/systec-backup-*.tar.gz.enc');
        $weeklyFiles = glob($backupDest . '/weekly/systec-weekly-*.tar.gz.enc');
        $monthlyFiles = glob($backupDest . '/monthly/systec-monthly-*.tar.gz.enc');

        self::assertIsArray($dailyFiles);
        self::assertCount(2, $dailyFiles);
        self::assertIsArray($weeklyFiles);
        self::assertCount(1, $weeklyFiles);
        self::assertIsArray($monthlyFiles);
        self::assertCount(1, $monthlyFiles);

        self::assertFileExists($logFile);
        $logContent = (string) file_get_contents($logFile);
        self::assertStringContainsString('STATUS=OK integrity_check=ok', $logContent);
        self::assertStringContainsString('encrypted=1', $logContent);
    }

    public function testAdminSaveUserRejectsCaseInsensitiveDuplicatesAndClearsThrottleOnPasswordReset(): void
    {
        $db = new DB(':memory:');
        $username = 'tech_case_test_' . bin2hex(random_bytes(4));
        $testIp = '198.51.100.' . random_int(1, 254);

        try {
            $adminId = (int) $db->create('users', array(
                'username' => 'admin_test',
                'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'active' => 1,
            ));

            $created = auth_admin_save_user(array(
                'username' => $username,
                'role' => 'tecnico',
                'active' => '1',
                'password' => 'initpass1',
            ), $adminId, $db);
            self::assertTrue($created['ok']);

            $duplicate = auth_admin_save_user(array(
                'username' => strtoupper($username),
                'role' => 'consulta',
                'active' => '1',
                'password' => 'initpass2',
            ), $adminId, $db);
            self::assertFalse($duplicate['ok']);

            // Simula 5 intentos fallidos para bloquear la cuenta
            for ($i = 0; $i < 5; $i++) {
                auth_throttle_record_failure($username, $testIp);
            }
            self::assertTrue(auth_throttle_check($username, $testIp)['blocked']);

            // Al restablecer contraseña desde admin, debe limpiar automáticamente el bloqueo
            $updated = auth_admin_save_user(array(
                'user_id' => (string) $created['id'],
                'role' => 'tecnico',
                'active' => '1',
                'password' => 'newpass123',
            ), $adminId, $db);
            self::assertTrue($updated['ok']);
            self::assertFalse(auth_throttle_check($username, $testIp)['blocked']);
        } finally {
            auth_throttle_clear($username, $testIp);
            @unlink(auth_throttle_directory() . DIRECTORY_SEPARATOR . 'ip_' . hash('sha256', $testIp) . '.json');
            $db->close();
        }
    }

    public function testAdminDeleteUserEnforcesDoubleConfirmationAndAdminGuards(): void
    {
        $db = new DB(':memory:');
        $victimUsername = 'delete_me_' . bin2hex(random_bytes(4));

        try {
            $adminId = (int) $db->create('users', array(
                'username' => 'sole_admin',
                'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'active' => 1,
            ));
            $victimId = (int) $db->create('users', array(
                'username' => $victimUsername,
                'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
                'role' => 'consulta',
                'active' => 1,
            ));

            // No puede eliminarse a sí mismo
            $selfAttempt = auth_admin_delete_user($adminId, $adminId, 'sole_admin', true, $db);
            self::assertFalse($selfAttempt['ok']);

            // Sin confirmación de riesgo falla
            $noRisk = auth_admin_delete_user($victimId, $adminId, $victimUsername, false, $db);
            self::assertFalse($noRisk['ok']);

            // Con username incorrecto falla
            $wrongName = auth_admin_delete_user($victimId, $adminId, 'otro_usuario', true, $db);
            self::assertFalse($wrongName['ok']);

            // No puede eliminar al último admin activo (aunque lo intente otro actor)
            $lastAdmin = auth_admin_delete_user($adminId, $victimId, 'sole_admin', true, $db);
            self::assertFalse($lastAdmin['ok']);

            // Con doble confirmación válida elimina permanentemente
            $deleted = auth_admin_delete_user($victimId, $adminId, $victimUsername, true, $db);
            self::assertTrue($deleted['ok']);
            self::assertNull($db->getFirstForId('users', $victimId));
        } finally {
            auth_throttle_clear($victimUsername, '0.0.0.0');
            $db->close();
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
