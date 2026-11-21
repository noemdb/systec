<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/inventory_import.php';

use PHPUnit\Framework\TestCase;

class HomeSummaryQueryTest extends TestCase
{
    public function testPropertyStatusSummaryCountsMatchInMemorySummaryInOneQuery(): void
    {
        $db = new DB(':memory:');

        try {
            $db->create('properties', array('ident' => '0001', 'status' => 'BUENO'));
            $db->create('properties', array('ident' => '0002', 'status' => ' bueno '));
            $db->create('properties', array('ident' => '0003', 'status' => 'REGULAR'));
            $db->create('properties', array('ident' => '0004', 'status' => 'MALO'));
            $db->create('properties', array('ident' => '0005', 'status' => 'muy malo'));
            $db->create('properties', array('ident' => '0006', 'status' => ''));

            $allProperties = $db->index('properties', array(), 'id,status', 'id ASC');
            $expected = $db->properties()->summarizeStatuses($allProperties);

            $db->resetQueryCount();
            $actual = $db->properties()->statusSummaryCounts();

            self::assertSame($expected, $actual);
            self::assertSame(array('total' => 6, 'good' => 2, 'attention' => 2), $actual);
            self::assertSame(1, $db->queryCount());
        } finally {
            $db->close();
        }
    }

    public function testPropertyStatusSummaryCountsReturnZerosOnEmptyTable(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertSame(
                array('total' => 0, 'good' => 0, 'attention' => 0),
                $db->properties()->statusSummaryCounts()
            );
        } finally {
            $db->close();
        }
    }

    public function testMaintenanceDashboardSummaryCountsMatchInMemorySummaryInOneQuery(): void
    {
        $db = new DB(':memory:');

        try {
            $now = strtotime('2026-06-15 12:00:00');
            $propId = $db->create('properties', array('ident' => '0001', 'status' => 'BUENO'));

            // Iniciado vencido
            $db->create('maintenances', array(
                'property_id' => $propId,
                'status' => 'iniciado',
                'next_maintenance_date' => '2026-06-10 08:00:00',
            ));
            // Revisión vencido (solo fecha)
            $db->create('maintenances', array(
                'property_id' => $propId,
                'status' => 'revision',
                'next_maintenance_date' => '2026-06-14',
            ));
            // Iniciado futuro (no vencido)
            $db->create('maintenances', array(
                'property_id' => $propId,
                'status' => 'iniciado',
                'next_maintenance_date' => '2026-06-20',
            ));
            // Finalizado con fecha pasada (no cuenta como vencido)
            $db->create('maintenances', array(
                'property_id' => $propId,
                'status' => 'finalizado',
                'next_maintenance_date' => '2026-05-01',
            ));
            // Fecha inválida (no cuenta como vencido)
            $db->create('maintenances', array(
                'property_id' => $propId,
                'status' => 'iniciado',
                'next_maintenance_date' => 'fecha-invalida',
            ));

            $allMaintenances = $db->index('maintenances', array(), 'id,status,next_maintenance_date', 'id DESC');
            $expected = $db->maintenances()->summarizeCounts($allMaintenances, $now);

            $db->resetQueryCount();
            $actual = $db->maintenances()->dashboardSummaryCounts($now);

            self::assertSame($expected, $actual);
            self::assertSame(
                array('total' => 5, 'iniciado' => 3, 'revision' => 1, 'finalizado' => 1, 'vencido' => 2),
                $actual
            );
            self::assertSame(1, $db->queryCount());
        } finally {
            $db->close();
        }
    }

    public function testDamagedReportCountDeduplicatesPropertiesInOneQuery(): void
    {
        $db = new DB(':memory:');

        try {
            $badProp = $db->create('properties', array('ident' => '0001', 'status' => 'MALO'));
            $veryBadProp = $db->create('properties', array('ident' => '0002', 'status' => 'MUY MALO'));
            $goodProp = $db->create('properties', array('ident' => '0003', 'status' => 'BUENO'));

            // Dos mantenimientos con nota de desincorporación sobre el mismo bien
            $db->create('maintenances', array('property_id' => $badProp, 'notes' => 'Sugiere desincorporación inmediata'));
            $db->create('maintenances', array('property_id' => $badProp, 'notes' => 'Confirmada desincorporación'));
            // Otro bien muy malo
            $db->create('maintenances', array('property_id' => $veryBadProp, 'notes' => 'Informe de desincorporación'));
            // Bien bueno con nota de desincorporación (no debe contar)
            $db->create('maintenances', array('property_id' => $goodProp, 'notes' => 'desincorporación'));

            $expectedCount = count($db->indexWithMaintenances('properties.*', 'date ASC'));

            $db->resetQueryCount();
            $actualCount = $db->maintenances()->damagedReportCount();

            self::assertSame($expectedCount, $actualCount);
            self::assertSame(2, $actualCount);
            self::assertSame(1, $db->queryCount());
        } finally {
            $db->close();
        }
    }

    public function testMaintenancesGroupedByPropertyIdsPreloadsInSingleQuery(): void
    {
        $db = new DB(':memory:');

        try {
            $prop1 = $db->create('properties', array('ident' => '0101', 'status' => 'MALO'));
            $prop2 = $db->create('properties', array('ident' => '0102', 'status' => 'MUY MALO'));
            $prop3 = $db->create('properties', array('ident' => '0103', 'status' => 'BUENO'));

            $db->create('maintenances', array('property_id' => $prop1, 'date' => '2026-02-10', 'description' => 'Segundo'));
            $db->create('maintenances', array('property_id' => $prop1, 'date' => '2026-01-05', 'description' => 'Primero'));
            $db->create('maintenances', array('property_id' => $prop2, 'date' => '2026-03-01', 'description' => 'Unico'));
            $db->create('maintenances', array('property_id' => $prop3, 'date' => '2026-03-02', 'description' => 'Ignorado'));

            $db->resetQueryCount();
            $grouped = $db->maintenances()->maintenancesGroupedByPropertyIds(array($prop1, $prop2, 'invalid', -1));

            self::assertSame(1, $db->queryCount());
            self::assertArrayHasKey($prop1, $grouped);
            self::assertArrayHasKey($prop2, $grouped);
            self::assertArrayNotHasKey($prop3, $grouped);
            self::assertCount(2, $grouped[$prop1]);
            self::assertSame('Primero', $grouped[$prop1][0]['description']);
            self::assertSame('Segundo', $grouped[$prop1][1]['description']);
            self::assertCount(1, $grouped[$prop2]);
            self::assertSame(array(), $db->maintenances()->maintenancesGroupedByPropertyIds(array()));
        } finally {
            $db->close();
        }
    }

    public function testUrgentMaintenancesPrioritizesOverdueAndUpcomingWithinWindow(): void
    {
        $db = new DB(':memory:');

        try {
            $now = strtotime('2026-06-15 12:00:00');
            $propA = $db->create('properties', array('ident' => '0010', 'description' => 'Equipo A'));
            $propB = $db->create('properties', array('ident' => '0011', 'description' => 'Equipo B'));

            $db->create('maintenances', array(
                'property_id' => $propA,
                'status' => 'iniciado',
                'next_maintenance_date' => '2026-06-10', // vencido
            ));
            $db->create('maintenances', array(
                'property_id' => $propB,
                'status' => 'revision',
                'next_maintenance_date' => '2026-06-18', // próximo (3 días)
            ));
            $db->create('maintenances', array(
                'property_id' => $propA,
                'status' => 'iniciado',
                'next_maintenance_date' => '2026-07-01', // fuera de ventana de 7 días
            ));
            $db->create('maintenances', array(
                'property_id' => $propA,
                'status' => 'finalizado',
                'next_maintenance_date' => '2026-06-01', // finalizado
            ));

            $urgent = $db->maintenances()->urgentMaintenances(5, $now, 7);

            self::assertCount(2, $urgent);
            self::assertSame('0010', str_pad((string) $urgent[0]['property_ident'], 4, '0', STR_PAD_LEFT));
            self::assertSame(1, (int) $urgent[0]['is_overdue']);
            self::assertSame('0011', str_pad((string) $urgent[1]['property_ident'], 4, '0', STR_PAD_LEFT));
            self::assertSame(0, (int) $urgent[1]['is_overdue']);
        } finally {
            $db->close();
        }
    }

    public function testUrgentOpenTicketsPrioritizesDelayedAndExcludesClosedOrDeleted(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));

            $propId = $db->create('properties', array('ident' => '0020', 'description' => 'Impresora'));
            $userId = $db->create('users', array('username' => 'tech', 'password_hash' => 'hash', 'role' => 'tecnico'));

            $iniciado = $db->getTicketStatusByCode('iniciado');
            $conRetraso = $db->getTicketStatusByCode('con_retraso');
            $finalizado = $db->getTicketStatusByCode('finalizado');

            $db->create('tickets', array(
                'property_id' => $propId,
                'status_id' => (int) $iniciado['id'],
                'title' => 'Ticket reciente iniciado',
                'description' => 'Detalle',
                'created_by' => $userId,
                'updated_at' => '2026-06-15 11:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $propId,
                'status_id' => (int) $conRetraso['id'],
                'title' => 'Ticket retrasado',
                'description' => 'Detalle',
                'created_by' => $userId,
                'updated_at' => '2026-06-10 09:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $propId,
                'status_id' => (int) $finalizado['id'],
                'title' => 'Ticket cerrado',
                'description' => 'Detalle',
                'created_by' => $userId,
                'updated_at' => '2026-06-15 12:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $propId,
                'status_id' => (int) $conRetraso['id'],
                'title' => 'Ticket eliminado',
                'description' => 'Detalle',
                'created_by' => $userId,
                'deleted_at' => '2026-06-15 12:00:00',
            ));

            $urgent = $db->tickets()->urgentOpenTickets(5);

            self::assertCount(2, $urgent);
            self::assertSame('Ticket retrasado', $urgent[0]['title']);
            self::assertSame('con_retraso', $urgent[0]['status_code']);
            self::assertSame('Ticket reciente iniciado', $urgent[1]['title']);
        } finally {
            $db->close();
        }
    }

    public function testAuthCanViewTicketsIncludesConsultaAndExcludesPersonal(): void
    {
        $previousUser = $_SESSION['user'] ?? null;

        try {
            foreach (array('admin', 'operador', 'tecnico', 'consulta') as $role) {
                $_SESSION['user'] = array('id' => 1, 'username' => 'u_' . $role, 'role' => $role);
                self::assertTrue(auth_can_view_tickets(), 'Role ' . $role . ' should be able to view tickets');
            }

            $_SESSION['user'] = array('id' => 2, 'username' => 'u_personal', 'role' => 'personal');
            self::assertFalse(auth_can_view_tickets());
        } finally {
            if ($previousUser === null) {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user'] = $previousUser;
            }
        }
    }

    public function testFormatRelativeAgeProducesReadableSpanishLabels(): void
    {
        self::assertSame('—', format_relative_age(null));
        self::assertSame('hace menos de 1 min', format_relative_age(30));
        self::assertSame('hace 5 min', format_relative_age(300));
        self::assertSame('hace 3 h', format_relative_age(3 * 3600 + 120));
        self::assertSame('hace 1 día', format_relative_age(86400));
        self::assertSame('hace 4 días', format_relative_age(4 * 86400 + 600));
    }

    public function testLegacyLocalCacheRespectsTtlForStaleness(): void
    {
        $cacheDir = SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0750, true);
        }

        $testFile = $cacheDir . DIRECTORY_SEPARATOR . 'inventory-adscriptions-phpunit-ttl-test.json';
        $cachedAt = time() - 7200; // 2 horas atrás
        file_put_contents($testFile, json_encode(array(
            'adscriptions' => array('0001' => array('key' => 'k1', 'label' => 'Unidad 1')),
            'assignments' => array('0001' => array('responsible_name' => 'Ana')),
            'cached_at' => $cachedAt,
            'stale' => false,
        )));
        // Asegurar que sea el archivo más reciente por mtime
        touch($testFile, time() + 10);

        try {
            $fresh = legacy_postgres_read_local_cache(86400, $cachedAt + 7200);
            self::assertTrue($fresh['found']);
            self::assertFalse($fresh['stale']);
            self::assertSame(7200, $fresh['age_seconds']);
            self::assertCount(1, $fresh['assignments']);

            $expired = legacy_postgres_read_local_cache(3600, $cachedAt + 7200);
            self::assertTrue($expired['found']);
            self::assertTrue($expired['stale']);
            self::assertSame(7200, $expired['age_seconds']);
        } finally {
            @unlink($testFile);
        }
    }
}
