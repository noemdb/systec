<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/nvidia_maintenance_narrative.php';

use PHPUnit\Framework\TestCase;

class RenditionReportTest extends TestCase
{
    public function testComputeScopeHashIsDeterministicAndSensitiveToPeriodAndFilters(): void
    {
        $db = new DB(':memory:');
        try {
            $repo = $db->renditionReports();

            $hashA = $repo->computeScopeHash('2026-11-01', '2026-11-30', array(
                'category' => 'informatica',
                'technician' => 'JMedina',
                'type' => 'preventivo',
            ));
            $hashB = $repo->computeScopeHash('2026-11-01', '2026-11-30', array(
                'type' => 'PREVENTIVO',
                'category' => 'INFORMATICA',
                'technician' => 'jmedina',
            ));
            $hashDifferentDate = $repo->computeScopeHash('2026-11-02', '2026-11-30', array(
                'category' => 'informatica',
                'technician' => 'jmedina',
                'type' => 'preventivo',
            ));

            self::assertSame($hashA, $hashB);
            self::assertNotSame($hashA, $hashDifferentDate);
        } finally {
            $db->close();
        }
    }

    public function testCollectPeriodDataGathersMaintenancesAndAssociatedTicketsAndRendersTemplate(): void
    {
        $db = new DB(':memory:');
        try {
            $db->exec((string) file_get_contents(__DIR__ . '/../../migrations/004_soft_delete_tickets.sql'));
            $db->exec((string) file_get_contents(__DIR__ . '/../../migrations/007_link_maintenances_to_tickets.sql'));
            $db->exec((string) file_get_contents(__DIR__ . '/../../migrations/008_create_rendition_reports.sql'));
            $repo = $db->renditionReports();

            $prop1 = (int) $db->create('properties', array(
                'grupo' => '2',
                'subgrupo' => '0101',
                'seccion' => '001',
                'ident' => '0045',
                'description' => 'COMPUTADOR DE ESCRITORIO DELL OPTIPLEX',
                'status' => 'BUENO',
            ));
            $prop2 = (int) $db->create('properties', array(
                'grupo' => '2',
                'subgrupo' => '0005',
                'seccion' => '003',
                'ident' => '0110',
                'description' => 'SWITCH DE RED 24 PUERTOS',
                'status' => 'REGULAR',
            ));

            $statusFinalizado = $db->getTicketStatusByCode('finalizado');
            $statusEnProgreso = $db->getTicketStatusByCode('en_progreso');

            $ticket1 = (int) $db->create('tickets', array(
                'property_id' => $prop1,
                'status_id' => (int) $statusFinalizado['id'],
                'title' => 'Falla de arranque en equipo Dell',
                'description' => 'El equipo no inicia el sistema operativo.',
                'observation' => 'Oficina de presupuesto.',
                'created_by' => 1,
                'created_at' => '2026-11-10 08:30:00',
                'updated_at' => '2026-11-10 14:00:00',
            ));
            $ticket2 = (int) $db->create('tickets', array(
                'property_id' => $prop2,
                'status_id' => (int) $statusEnProgreso['id'],
                'title' => 'Intermitencia en puerto uplink',
                'description' => 'Se reportan caídas breves de red.',
                'observation' => 'Cuarto de cableado.',
                'created_by' => 1,
                'created_at' => '2026-11-12 09:00:00',
                'updated_at' => '2026-11-12 11:30:00',
            ));

            // Mantenimiento dentro del rango con ticket directo
            $db->create('maintenances', array(
                'property_id' => $prop1,
                'ticket_id' => $ticket1,
                'code' => '2-0101-0001-0045',
                'type' => 'correctivo',
                'description' => 'Reparación de sector de arranque y limpieza de contactos de RAM.',
                'date' => '2026-11-10 10:00:00',
                'time_taken' => 4,
                'technician' => 'jmedina',
                'failure_reason' => 'Corte eléctrico abrupto.',
                'notes' => 'Equipo operativo y entregado al usuario.',
                'status' => 'finalizado',
            ));

            // Segundo mantenimiento dentro del rango
            $db->create('maintenances', array(
                'property_id' => $prop2,
                'code' => '2-0005-0003-0110',
                'type' => 'preventivo',
                'description' => 'Limpieza de ventiladores y verificación de latiguillos.',
                'date' => '2026-11-15 11:00:00',
                'time_taken' => 2,
                'technician' => 'ndominguez',
                'failure_reason' => 'Mantenimiento programado.',
                'notes' => 'Se mantiene monitoreo del puerto uplink.',
                'status' => 'revision',
            ));

            // Mantenimiento fuera del rango (octubre)
            $db->create('maintenances', array(
                'property_id' => $prop1,
                'code' => '2-0101-0001-0045',
                'type' => 'preventivo',
                'description' => 'Respaldo de archivos previo.',
                'date' => '2026-10-05 09:00:00',
                'time_taken' => 1,
                'technician' => 'jmedina',
                'status' => 'finalizado',
            ));

            $periodData = $repo->collectPeriodData('2026-11-01', '2026-11-30', array(), true);

            self::assertSame(2, $periodData['stats']['total_maintenances']);
            self::assertSame(2, $periodData['stats']['total_properties']);
            self::assertSame(6, $periodData['stats']['total_hours']);
            self::assertSame(2, $periodData['stats']['total_tickets']);
            self::assertSame(1, $periodData['stats']['tickets_closed']);
            self::assertSame(1, $periodData['stats']['tickets_open']);

            $parameters = $repo->normalizeParameters(array(
                'title' => 'INFORME DE RENDICIÓN MENSUAL DE NOVIEMBRE',
                'issuing_unit' => 'Oficina de Informática',
                'recipient_name' => 'Dirección General',
                'prepared_by' => 'jmedina',
                'include_tickets' => true,
                'custom_notes' => 'Se cumplió la meta mensual.',
            ));

            $aiSections = systec_generate_rendition_report_sections_with_fallback(array(
                'period_label' => '01/11/2026 al 30/11/2026',
                'scope_summary' => $periodData['scope_summary'],
                'stats' => $periodData['stats'],
                'maintenances' => $periodData['maintenances'],
                'tickets' => $periodData['tickets'],
                'custom_notes' => $parameters['custom_notes'],
            ), '');

            self::assertTrue($aiSections['fallback']);
            self::assertNotEmpty($aiSections['executive_summary']);
            self::assertNotEmpty($aiSections['activities_narrative']);
            self::assertNotEmpty($aiSections['tickets_narrative']);
            self::assertNotEmpty($aiSections['observations_and_conclusions']);

            $markdown = $repo->renderReportMarkdown(
                $periodData,
                $parameters,
                $aiSections,
                'IR-2026-0001',
                'borrador'
            );

            self::assertStringContainsString('# INFORME DE RENDICIÓN MENSUAL DE NOVIEMBRE', $markdown);
            self::assertStringContainsString('| **Código de Informe** | `IR-2026-0001` |', $markdown);
            self::assertStringContainsString('<th scope="col" rowspan="2" class="text-center">#</th>', $markdown);
            self::assertStringContainsString('<th scope="col" colspan="8" class="small text-body-secondary fw-semibold">Trabajo Realizado / Causa / Notas</th>', $markdown);
            self::assertStringContainsString('<td rowspan="2" class="text-center fw-semibold">1</td>', $markdown);
            self::assertStringContainsString('<td colspan="8" class="bg-body-tertiary small"><strong>Trabajo Realizado:</strong>', $markdown);
            self::assertStringContainsString('2-0101-0001-0045', $markdown);
            self::assertStringContainsString('2-0005-0003-0110', $markdown);
            self::assertStringContainsString('Falla de arranque en equipo Dell', $markdown);
            self::assertStringContainsString('Intermitencia en puerto uplink', $markdown);
        } finally {
            $db->close();
        }
    }

    public function testSaveReportEnforcesScopeUniquenessDraftUpdatesAndApprovalImmutability(): void
    {
        $db = new DB(':memory:');
        try {
            $repo = $db->renditionReports();

            // 1. Guardar borrador inicial
            $draft = $repo->saveReport(array(
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-15',
                'filters' => array('category' => 'informatica'),
                'parameters' => array('title' => 'Informe Quincenal', 'prepared_by' => 'jmedina'),
                'stats' => array('total_maintenances' => 3, 'total_tickets' => 1),
                'maintenance_ids' => array(1, 2, 3),
                'ticket_ids' => array(10),
                'content_markdown' => "# Informe Quincenal\n\n**Estado de Rendición:** BORRADOR EN REVISIÓN",
                'ai_generated' => true,
                'status' => 'borrador',
            ), 1);

            self::assertSame('IR-2026-0001', $draft['report_code']);
            self::assertSame('borrador', $draft['status']);

            // 2. Guardar de nuevo sobre el mismo rango/alcance actualiza el mismo borrador (mismo ID y código)
            $updatedDraft = $repo->saveReport(array(
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-15',
                'filters' => array('category' => 'informatica'),
                'parameters' => array('title' => 'Informe Quincenal Editado', 'prepared_by' => 'jmedina'),
                'stats' => array('total_maintenances' => 3, 'total_tickets' => 1),
                'maintenance_ids' => array(1, 2, 3),
                'ticket_ids' => array(10),
                'content_markdown' => "# Informe Quincenal Editado\n\nContenido modificado por usuario.",
                'ai_generated' => true,
                'status' => 'borrador',
            ), 1);

            self::assertSame($draft['id'], $updatedDraft['id']);
            self::assertSame('IR-2026-0001', $updatedDraft['report_code']);
            self::assertStringContainsString('Contenido modificado por usuario.', $updatedDraft['content_markdown']);

            // 3. Aprobar y cerrar el informe
            $approved = $repo->saveReport(array(
                'id' => $draft['id'],
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-15',
                'filters' => array('category' => 'informatica'),
                'parameters' => array('title' => 'Informe Quincenal Definitivo', 'prepared_by' => 'jmedina'),
                'stats' => array('total_maintenances' => 3, 'total_tickets' => 1),
                'maintenance_ids' => array(1, 2, 3),
                'ticket_ids' => array(10),
                'content_markdown' => "# Informe Quincenal Definitivo\n\n**Estado de Rendición:** BORRADOR EN REVISIÓN",
                'ai_generated' => true,
                'status' => 'aprobado',
            ), 2);

            self::assertSame('aprobado', $approved['status']);
            self::assertSame(2, $approved['approved_by']);
            self::assertNotEmpty($approved['approved_at']);
            self::assertStringContainsString('APROBADO', $approved['content_markdown']);

            // 4. Otro alcance o período recibe el siguiente correlativo único IR-2026-0002
            $secondReport = $repo->saveReport(array(
                'date_from' => '2026-11-16',
                'date_to' => '2026-11-30',
                'filters' => array('category' => 'informatica'),
                'parameters' => array('title' => 'Informe Segunda Quincena', 'prepared_by' => 'jmedina'),
                'stats' => array('total_maintenances' => 2, 'total_tickets' => 0),
                'content_markdown' => '# Informe Segunda Quincena',
                'status' => 'borrador',
            ), 1);
            self::assertSame('IR-2026-0002', $secondReport['report_code']);
            self::assertSame(2, $repo->countReports());

            // 5. Eliminar un informe funciona y actualiza el conteo
            self::assertTrue($repo->deleteReport((int) $secondReport['id']));
            self::assertNull($repo->findById((int) $secondReport['id']));
            self::assertSame(1, $repo->countReports());
            self::assertFalse($repo->deleteReport((int) $secondReport['id']));

            // 6. Intentar sobrescribir el primer período ya aprobado debe lanzar RuntimeException
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Ya existe un Informe de Rendición aprobado');
            $repo->saveReport(array(
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-15',
                'filters' => array('category' => 'informatica'),
                'parameters' => array('title' => 'Intento Duplicado'),
                'content_markdown' => '# Duplicado',
                'status' => 'borrador',
            ), 1);
        } finally {
            $db->close();
        }
    }
}
