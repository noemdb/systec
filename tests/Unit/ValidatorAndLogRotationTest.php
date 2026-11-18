<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/maintenance_validator.php';

use PHPUnit\Framework\TestCase;

class ValidatorAndLogRotationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_val_log_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0750, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testCentralizedMaintenanceCreateValidatorEnforcesAllRules(): void
    {
        $db = new DB(':memory:');

        try {
            $validInput = array(
                'property_id' => 1,
                'type' => 'preventivo',
                'technician' => 'jmedina',
                'date' => '2026-11-18 10:00:00',
                'next_maintenance_date' => '2027-02-18',
                'failure_reason' => 'Mantenimiento periódico',
                'description' => 'Revisión y limpieza general',
            );

            self::assertNull(validate_maintenance_create($validInput, $db));

            self::assertSame(
                'Selecciona un bien válido.',
                validate_maintenance_create(array_merge($validInput, array('property_id' => 0)), $db)
            );
            self::assertSame(
                'Selecciona un tipo de mantenimiento válido.',
                validate_maintenance_create(array_merge($validInput, array('type' => 'inexistente')), $db)
            );
            self::assertSame(
                'Selecciona un técnico válido.',
                validate_maintenance_create(array_merge($validInput, array('technician' => 'desconocido')), $db)
            );
            self::assertSame(
                'La fecha de inicio es obligatoria.',
                validate_maintenance_create(array_merge($validInput, array('date' => '')), $db)
            );
            self::assertSame(
                'La fecha de inicio no es válida.',
                validate_maintenance_create(array_merge($validInput, array('date' => 'fecha-invalida')), $db)
            );
            self::assertSame(
                'La fecha del próximo mantenimiento no es válida.',
                validate_maintenance_create(array_merge($validInput, array('next_maintenance_date' => 'no-es-fecha')), $db)
            );
            self::assertSame(
                'La razón del mantenimiento es obligatoria.',
                validate_maintenance_create(array_merge($validInput, array('failure_reason' => '   ')), $db)
            );
            self::assertSame(
                'La descripción es obligatoria.',
                validate_maintenance_create(array_merge($validInput, array('description' => '')), $db)
            );
        } finally {
            $db->close();
        }
    }

    public function testCentralizedMaintenanceUpdateAndTransitionValidatorsCheckStatusAndStateMachine(): void
    {
        $db = new DB(':memory:');

        try {
            $baseUpdate = array(
                'id' => 10,
                'property_id' => 1,
                'type' => 'correctivo',
                'technician' => 'ndominguez',
                'date' => '2026-11-18 10:00:00',
                'next_maintenance_date' => '',
                'failure_reason' => 'Falla eléctrica',
                'description' => 'Sustitución de fuente',
                'status' => 'revision',
            );

            self::assertNull(
                validate_maintenance_update($baseUpdate, $db, array('status' => 'iniciado'))
            );
            self::assertSame(
                'El identificador del mantenimiento es obligatorio.',
                validate_maintenance_update(array_merge($baseUpdate, array('id' => '')), $db)
            );
            self::assertSame(
                'El estado del mantenimiento no es válido.',
                validate_maintenance_update(array_merge($baseUpdate, array('status' => 'cancelado')), $db)
            );
            self::assertSame(
                'La transición de estado no está permitida.',
                validate_maintenance_update(
                    array_merge($baseUpdate, array('status' => 'iniciado')),
                    $db,
                    array('status' => 'finalizado')
                )
            );

            // Contexto transition
            self::assertNull(
                validate_maintenance_transition(10, 'finalizado', $db, array('status' => 'revision'))
            );
            self::assertSame(
                'Datos incompletos.',
                validate_maintenance_transition(0, 'finalizado', $db)
            );
            self::assertSame(
                'Estado de mantenimiento inválido.',
                validate_maintenance_transition(10, 'pendiente', $db)
            );
            self::assertSame(
                'La transición de estado no está permitida.',
                validate_maintenance_transition(10, 'iniciado', $db, array('status' => 'revision'))
            );
        } finally {
            $db->close();
        }
    }

    public function testLogRotationShiftsArchivesAndSanitizesInjectedNewlines(): void
    {
        $logFile = $this->tempDir . DIRECTORY_SEPARATOR . 'app.log';

        // Escribir entradas hasta superar maxBytes = 120 bytes con maxArchives = 2
        systec_log('INFO', "Primera línea\ncon salto inyectado", $logFile, 120, 2);
        self::assertFileExists($logFile);
        $firstContent = (string) file_get_contents($logFile);
        self::assertStringContainsString('[INFO] Primera línea con salto inyectado', $firstContent);
        self::assertSame(1, substr_count($firstContent, "\n"));

        // Llenar archivo para forzar primera rotación (.1)
        file_put_contents($logFile, str_repeat('A', 150) . "\n");
        systec_log('WARNING', 'Mensaje tras primera rotación', $logFile, 120, 2);
        self::assertFileExists($logFile . '.1');
        self::assertStringContainsString('AAAAAAA', (string) file_get_contents($logFile . '.1'));
        self::assertStringContainsString('[WARNING] Mensaje tras primera rotación', (string) file_get_contents($logFile));

        // Forzar segunda rotación (.1 -> .2) y tercera rotación (descarta el más antiguo > .2)
        file_put_contents($logFile, str_repeat('B', 150) . "\n");
        systec_log('ERROR', 'Mensaje tras segunda rotación', $logFile, 120, 2);
        self::assertFileExists($logFile . '.2');
        self::assertStringContainsString('AAAAAAA', (string) file_get_contents($logFile . '.2'));
        self::assertStringContainsString('BBBBBBB', (string) file_get_contents($logFile . '.1'));

        file_put_contents($logFile, str_repeat('C', 150) . "\n");
        systec_log('SECURITY', 'Mensaje tras tercera rotación', $logFile, 120, 2);
        self::assertFileDoesNotExist($logFile . '.3');
        self::assertStringContainsString('BBBBBBB', (string) file_get_contents($logFile . '.2'));
        self::assertStringContainsString('CCCCCCC', (string) file_get_contents($logFile . '.1'));
        self::assertStringContainsString('[SECURITY] Mensaje tras tercera rotación', (string) file_get_contents($logFile));
    }
}
