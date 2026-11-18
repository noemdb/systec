<?php

require_once __DIR__ . '/../../include/auth.php';

use PHPUnit\Framework\TestCase;

class AuthThrottleTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_throttle_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0750, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*.json');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testInitialStateIsNotBlocked(): void
    {
        $status = auth_throttle_check('operador', '192.168.1.100', 100000, $this->tempDir);

        self::assertFalse($status['blocked']);
        self::assertSame(5, $status['remaining']);
        self::assertSame(0, $status['retry_after']);
        self::assertSame(0, $status['retry_minutes']);
    }

    public function testFailedAttemptsDecreaseRemainingCount(): void
    {
        $username = 'operador';
        $ip = '192.168.1.100';
        $time = 100000;

        auth_throttle_record_failure($username, $ip, $time, $this->tempDir);
        $status1 = auth_throttle_check($username, $ip, $time, $this->tempDir);
        self::assertFalse($status1['blocked']);
        self::assertSame(4, $status1['remaining']);

        auth_throttle_record_failure($username, $ip, $time + 10, $this->tempDir);
        auth_throttle_record_failure($username, $ip, $time + 20, $this->tempDir);
        $status3 = auth_throttle_check($username, $ip, $time + 30, $this->tempDir);
        self::assertFalse($status3['blocked']);
        self::assertSame(2, $status3['remaining']);
    }

    public function testFiveFailuresBlockUserAccount(): void
    {
        $username = 'admin';
        $ip = '192.168.1.50';
        $baseTime = 100000;

        for ($i = 0; $i < 5; $i++) {
            auth_throttle_record_failure($username, $ip, $baseTime + ($i * 10), $this->tempDir);
        }

        $status = auth_throttle_check($username, $ip, $baseTime + 100, $this->tempDir);

        self::assertTrue($status['blocked']);
        self::assertSame(0, $status['remaining']);
        self::assertSame('cuenta de usuario', $status['reason']);
        self::assertGreaterThan(0, $status['retry_after']);
        self::assertSame(14, $status['retry_minutes']);
    }

    public function testAttemptsExpireAfterWindowDuration(): void
    {
        $username = 'tecnico';
        $ip = '192.168.1.75';
        $oldTime = 100000;

        // Registrar 4 fallos en oldTime
        for ($i = 0; $i < 4; $i++) {
            auth_throttle_record_failure($username, $ip, $oldTime + $i, $this->tempDir);
        }

        // Consultar 16 minutos más tarde (> 900 segundos)
        $futureTime = $oldTime + 960;
        $status = auth_throttle_check($username, $ip, $futureTime, $this->tempDir);

        self::assertFalse($status['blocked']);
        self::assertSame(5, $status['remaining']);
    }

    public function testSuccessfulClearResetsUserThrottle(): void
    {
        $username = 'consulta';
        $ip = '192.168.1.80';
        $time = 100000;

        for ($i = 0; $i < 3; $i++) {
            auth_throttle_record_failure($username, $ip, $time + $i, $this->tempDir);
        }

        $beforeClear = auth_throttle_check($username, $ip, $time + 10, $this->tempDir);
        self::assertSame(2, $beforeClear['remaining']);

        auth_throttle_clear($username, $ip, $this->tempDir);

        $afterClear = auth_throttle_check($username, $ip, $time + 10, $this->tempDir);
        self::assertFalse($afterClear['blocked']);
        self::assertSame(5, $afterClear['remaining']);
    }

    public function testExceedingMaxIpAttemptsBlocksByIpAddress(): void
    {
        $ip = '10.0.0.99';
        $baseTime = 100000;

        // Simular ataque distribuido probando diferentes usuarios desde la misma IP
        for ($i = 1; $i <= 15; $i++) {
            auth_throttle_record_failure('user_' . $i, $ip, $baseTime + $i, $this->tempDir);
        }

        // Consultar un usuario nuevo que nunca falló
        $status = auth_throttle_check('fresh_user', $ip, $baseTime + 100, $this->tempDir);

        self::assertTrue($status['blocked']);
        self::assertSame('dirección IP', $status['reason']);
        self::assertSame(0, $status['remaining']);
    }
}
