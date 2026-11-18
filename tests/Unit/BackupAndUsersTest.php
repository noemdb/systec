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
