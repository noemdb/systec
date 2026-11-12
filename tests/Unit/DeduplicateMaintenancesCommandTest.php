<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class DeduplicateMaintenancesCommandTest extends TestCase
{
    public function testCommandDeduplicatesMatchingReportEntriesAndPreservesDifferentObservations(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'systec-maintenance-dedup-');
        self::assertNotFalse($databasePath);
        $db = new DB($databasePath);

        try {
            $propertyId = $db->create('properties', array('ident' => 'dedup-test'));
            $maintenance = array(
                'property_id' => $propertyId,
                'code' => '2-0101-0005-0131',
                'type' => 'preventivo',
                'description' => 'Revisión del equipo',
                'date' => '2014-10-24',
                'time_taken' => 30,
                'technician' => 'ndominguez',
                'next_maintenance_date' => null,
                'failure_reason' => null,
                'notes' => null,
                'status' => 'finalizado',
            );
            $keptId = $db->create('maintenances', $maintenance);
            $db->create('maintenances', array_merge($maintenance, array(
                'code' => 201010050131,
                'time_taken' => 90,
                'failure_reason' => 'Otra descripción de falla',
            )));
            $db->create('maintenances', array_merge($maintenance, array(
                'code' => 201010050131,
                'time_taken' => 46,
                'next_maintenance_date' => '2015-10-24',
            )));
            $db->create('maintenances', array_merge($maintenance, array(
                'code' => 201010050131,
                'time_taken' => 28,
            )));
            $differentDateId = $db->create('maintenances', array_merge($maintenance, array(
                'date' => '2014-10-25',
            )));
            $differentNotesId = $db->create('maintenances', array_merge($maintenance, array(
                'notes' => 'Observación distinta',
            )));
            $db->close();
            $db = null;

            list($previewStatus, $previewOutput) = $this->runCommand($databasePath);
            self::assertSame(0, $previewStatus, $previewOutput);
            self::assertStringContainsString('Grupos duplicados: 1', $previewOutput);
            self::assertStringContainsString('Registros sobrantes: 3', $previewOutput);
            self::assertStringContainsString('Vista previa', $previewOutput);

            $db = new DB($databasePath);
            self::assertSame(6, $db->countRows('maintenances'));
            $db->close();
            $db = null;

            list($executeStatus, $executeOutput) = $this->runCommand($databasePath, '--execute');
            self::assertSame(0, $executeStatus, $executeOutput);
            self::assertStringContainsString('Registros eliminados: 3', $executeOutput);

            $db = new DB($databasePath);
            $remaining = $db->index('maintenances', array(), 'id', 'id ASC');
            self::assertSame(3, count($remaining));
            self::assertSame($keptId, (int) $remaining[0]['id']);
            self::assertSame($differentDateId, (int) $remaining[1]['id']);
            self::assertSame($differentNotesId, (int) $remaining[2]['id']);
            $db->close();
            $db = null;
        } finally {
            if ($db instanceof DB) {
                $db->close();
            }
            foreach (array('', '-journal', '-wal', '-shm') as $suffix) {
                $path = $databasePath . $suffix;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function runCommand(string $databasePath, string $option = ''): array
    {
        $command = array(PHP_BINARY, __DIR__ . '/../../bin/deduplicate-maintenances.php');
        if ($option !== '') {
            $command[] = $option;
        }

        $environment = getenv();
        if (!is_array($environment)) {
            $environment = array();
        }
        $environment['SYSTEC_DB'] = $databasePath;

        $process = proc_open(
            $command,
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'),
            ),
            $pipes,
            null,
            $environment
        );
        self::assertIsResource($process, 'No se pudo iniciar el comando de deduplicación.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return array($exitCode, $stdout . $stderr);
    }
}
