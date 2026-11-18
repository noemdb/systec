<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

use PHPUnit\Framework\TestCase;

class SecurityAndTransitionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = array();
        $_POST = array();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard.php';
    }

    public function testRoleMatrixPermissionsAndTicketScope(): void
    {
        self::assertFalse(auth_check());
        self::assertFalse(auth_has_role(array('admin')));
        self::assertFalse(auth_can_manage_all_tickets());
        self::assertFalse(auth_can_access_ticket_for_user(1));

        $matrix = array(
            'admin' => array('manage_all_tickets' => true, 'mutate_inventory' => true, 'mutate_maintenances' => true),
            'operador' => array('manage_all_tickets' => true, 'mutate_inventory' => true, 'mutate_maintenances' => true),
            'tecnico' => array('manage_all_tickets' => true, 'mutate_inventory' => false, 'mutate_maintenances' => true),
            'consulta' => array('manage_all_tickets' => false, 'mutate_inventory' => false, 'mutate_maintenances' => false),
            'personal' => array('manage_all_tickets' => false, 'mutate_inventory' => false, 'mutate_maintenances' => false),
        );

        foreach ($matrix as $role => $expected) {
            $_SESSION['user'] = array('id' => 10, 'username' => 'u_' . $role, 'role' => $role);

            self::assertTrue(auth_check());
            self::assertSame($expected['manage_all_tickets'], auth_can_manage_all_tickets(), "Fallo en manage_all_tickets para rol {$role}");
            self::assertSame($expected['mutate_inventory'], auth_has_role(array('admin', 'operador')), "Fallo en mutate_inventory para rol {$role}");
            self::assertSame($expected['mutate_maintenances'], auth_has_role(array('admin', 'operador', 'tecnico')), "Fallo en mutate_maintenances para rol {$role}");

            if ($role === 'personal') {
                self::assertTrue(auth_can_access_ticket_for_user(10));
                self::assertFalse(auth_can_access_ticket_for_user(99));
            } elseif ($expected['manage_all_tickets']) {
                self::assertTrue(auth_can_access_ticket_for_user(99));
            } else {
                self::assertFalse(auth_can_access_ticket_for_user(10));
            }
        }
    }

    public function testRequireRoleReturns403WhenRoleIsUnauthorized(): void
    {
        $result = $this->runIsolatedAuthSnippet('
            $_SESSION["user"] = array("id" => 4, "username" => "lector", "role" => "consulta");
            require_role(array("admin", "operador"));
        ');

        self::assertSame(403, $result['status']);
        self::assertStringContainsString('Acceso denegado', $result['body']);
    }

    public function testRequireRoleAllowsAuthorizedRole(): void
    {
        $result = $this->runIsolatedAuthSnippet('
            $_SESSION["user"] = array("id" => 1, "username" => "admin", "role" => "admin");
            require_role(array("admin", "operador"));
            echo "AUTHORIZED_OK";
        ');

        self::assertSame(200, $result['status']);
        self::assertSame('AUTHORIZED_OK', $result['body']);
    }

    public function testVerifyCsrfReturns405ForNonPostRequests(): void
    {
        $result = $this->runIsolatedAuthSnippet('
            $_SERVER["REQUEST_METHOD"] = "GET";
            verify_csrf();
        ');

        self::assertSame(405, $result['status']);
        self::assertStringContainsString('Método no permitido', $result['body']);
    }

    public function testVerifyCsrfReturns419WhenTokenIsMissingOrInvalid(): void
    {
        $missingSessionResult = $this->runIsolatedAuthSnippet('
            $_SERVER["REQUEST_METHOD"] = "POST";
            $_POST["csrf_token"] = "token_sin_sesion";
            verify_csrf();
        ');
        self::assertSame(419, $missingSessionResult['status']);
        self::assertStringContainsString('Token CSRF inválido o expirado', $missingSessionResult['body']);

        $mismatchResult = $this->runIsolatedAuthSnippet('
            $_SERVER["REQUEST_METHOD"] = "POST";
            $_SESSION["csrf"] = "token_esperado_123456";
            $_POST["csrf_token"] = "token_distinto_654321";
            verify_csrf();
        ');
        self::assertSame(419, $mismatchResult['status']);
        self::assertStringContainsString('Token CSRF inválido o expirado', $mismatchResult['body']);
    }

    public function testVerifyCsrfAcceptsMatchingTokenAndCsrfFieldOutputsHiddenInput(): void
    {
        $token = csrf_token();
        self::assertSame(64, strlen($token));
        self::assertSame($token, csrf_token());

        $fieldHtml = csrf_field();
        self::assertStringContainsString('type="hidden"', $fieldHtml);
        self::assertStringContainsString('name="csrf_token"', $fieldHtml);
        self::assertStringContainsString('value="' . $token . '"', $fieldHtml);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = $token;
        verify_csrf();
        self::assertTrue(true);
    }

    public function testAuthIntendedUrlPreventsOpenRedirectAndRoutesByRole(): void
    {
        $_SESSION['user'] = array('id' => 1, 'username' => 'admin', 'role' => 'admin');

        $_SESSION['intended'] = '//evil.example.com/phish';
        self::assertSame('/dashboard.php', auth_intended_url());

        $_SESSION['intended'] = 'https://evil.example.com/phish';
        self::assertSame('/dashboard.php', auth_intended_url());

        $_SESSION['intended'] = "/maintenances.php\nSet-Cookie: hacked=1";
        self::assertSame('/dashboard.php', auth_intended_url());

        $_SESSION['intended'] = '/maintenances.php?estado=iniciado';
        self::assertSame('/maintenances.php?estado=iniciado', auth_intended_url());

        $_SESSION['user'] = array('id' => 9, 'username' => 'empleado', 'role' => 'personal');
        $_SESSION['intended'] = '';
        self::assertSame('/personal/index.php', auth_intended_url());
    }

    public function testMaintenanceAndPropertyDatabaseTriggersEnforceCatalogAndStateTransitions(): void
    {
        $db = new DB(':memory:');

        try {
            $migrationSql = file_get_contents(__DIR__ . '/../../migrations/001_normalize_legacy_data.sql');
            self::assertNotFalse($migrationSql);
            self::assertTrue($db->exec($migrationSql));

            $propertyId = $db->create('properties', array(
                'ident' => '0100',
                'description' => 'Servidor de aplicaciones',
                'status' => 'BUENO',
            ));

            // 1. Transición válida: iniciado -> revision -> finalizado -> revision
            $maintenanceId = $db->create('maintenances', array(
                'property_id' => $propertyId,
                'code' => '2-0101-0001-0100',
                'type' => 'preventivo',
                'description' => 'Limpieza interna',
                'date' => '2026-03-01 09:00:00',
                'technician' => 'jmedina',
                'status' => 'iniciado',
            ));

            self::assertSame(1, $db->update('maintenances', $maintenanceId, array('status' => 'revision')));
            self::assertSame(1, $db->update('maintenances', $maintenanceId, array('status' => 'finalizado')));
            self::assertSame(1, $db->update('maintenances', $maintenanceId, array('status' => 'revision')));

            // 2. Transición inválida: revision -> iniciado debe fallar por trigger SQLite
            $invalidTransitionFailed = false;
            try {
                @$db->update('maintenances', $maintenanceId, array('status' => 'iniciado'));
            } catch (RuntimeException $e) {
                $invalidTransitionFailed = strpos($e->getMessage(), 'transición de estado de mantenimiento inválida') !== false;
            }
            self::assertTrue($invalidTransitionFailed, 'El trigger debe rechazar la transición revision -> iniciado');

            // 3. Transición inválida: finalizado -> iniciado debe fallar por trigger SQLite
            self::assertSame(1, $db->update('maintenances', $maintenanceId, array('status' => 'finalizado')));
            $finalToStartedFailed = false;
            try {
                @$db->update('maintenances', $maintenanceId, array('status' => 'iniciado'));
            } catch (RuntimeException $e) {
                $finalToStartedFailed = strpos($e->getMessage(), 'transición de estado de mantenimiento inválida') !== false;
            }
            self::assertTrue($finalToStartedFailed, 'El trigger debe rechazar la transición finalizado -> iniciado');

            // 4. Estado de bien fuera de catálogo debe fallar por trigger SQLite
            $invalidPropertyStatusFailed = false;
            try {
                @$db->update('properties', $propertyId, array('status' => 'MUY BUENO'));
            } catch (RuntimeException $e) {
                $invalidPropertyStatusFailed = strpos($e->getMessage(), 'estado de bien inválido') !== false;
            }
            self::assertTrue($invalidPropertyStatusFailed, 'El trigger debe rechazar el estado de bien MUY BUENO');
        } finally {
            $db->close();
        }
    }

    /**
     * Ejecuta un fragmento PHP en un subproceso aislado para capturar el código HTTP
     * y la salida cuando require_role() o verify_csrf() invocan exit().
     */
    private function runIsolatedAuthSnippet(string $snippet): array
    {
        $authPath = var_export(realpath(__DIR__ . '/../../include/auth.php'), true);
        $runner = sprintf(
            '$_SESSION = array(); $_POST = array(); $_SERVER["REQUEST_METHOD"] = "GET"; '
            . 'require_once %s; '
            . 'register_shutdown_function(function () { '
            . '    $code = http_response_code(); '
            . '    fwrite(STDERR, "HTTP_STATUS=" . ($code ? $code : 200)); '
            . '}); '
            . '%s',
            $authPath,
            $snippet
        );

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process = proc_open(array(PHP_BINARY, '-r', $runner), $descriptors, $pipes);
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);

        $status = 200;
        if (preg_match('/HTTP_STATUS=(\d+)/', $stderr, $matches)) {
            $status = (int) $matches[1];
        }

        return array(
            'status' => $status,
            'body' => $stdout,
        );
    }
}
