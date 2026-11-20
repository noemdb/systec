<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class SupportTicketQueryTest extends TestCase
{
    public function testSupportTicketQueriesJoinAndPaginateInTwoConstantQueries(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));

            $creatorId = $db->create('users', array(
                'username' => 'solicitante01',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $techId = $db->create('users', array(
                'username' => 'tecnico01',
                'password_hash' => 'hash',
                'role' => 'tecnico',
            ));

            $propertyId = $db->create('properties', array(
                'grupo' => '2',
                'subgrupo' => '0101',
                'seccion' => '001',
                'ident' => '0042',
                'description' => 'Estación de trabajo',
                'status' => 'BUENO',
            ));

            $started = $db->getTicketStatusByCode('iniciado');
            $inProgress = $db->getTicketStatusByCode('en_progreso');
            $closed = $db->getTicketStatusByCode('finalizado');

            $createdTicketIds = array();
            for ($i = 1; $i <= 30; $i++) {
                $statusId = ($i % 3 === 0)
                    ? (int) $closed['id']
                    : (($i % 2 === 0) ? (int) $inProgress['id'] : (int) $started['id']);
                $ticketId = $db->create('tickets', array(
                    'property_id' => $propertyId,
                    'status_id' => $statusId,
                    'title' => 'Incidencia #' . $i,
                    'description' => 'Detalle de incidencia #' . $i,
                    'created_by' => $creatorId,
                    'created_at' => sprintf('2026-02-01 10:%02d:00', $i),
                    'updated_at' => sprintf('2026-02-01 11:%02d:00', $i),
                ));
                $createdTicketIds[] = $ticketId;

                $db->create('ticket_histories', array(
                    'ticket_id' => $ticketId,
                    'actor_user_id' => $creatorId,
                    'action_type' => 'created',
                    'description' => 'Ticket registrado.',
                    'created_at' => sprintf('2026-02-01 10:%02d:00', $i),
                ));
                $db->create('ticket_histories', array(
                    'ticket_id' => $ticketId,
                    'actor_user_id' => $techId,
                    'action_type' => 'comment',
                    'description' => 'Revisión técnica #' . $i,
                    'observation' => 'Observación #' . $i,
                    'created_at' => sprintf('2026-02-01 11:%02d:00', $i),
                ));
            }

            // Crear un ticket eliminado que no debe aparecer en conteos ni páginas
            $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $inProgress['id'],
                'title' => 'Ticket dado de baja',
                'description' => 'Oculto',
                'created_by' => $creatorId,
                'deleted_at' => '2026-02-02 09:00:00',
            ));

            // Medir consultas exactas para cargar la bandeja de soporte (PERF-02 <= 2 consultas)
            $db->resetQueryCount();

            $dashboardSummary = $db->tickets()->supportTicketDashboardSummary();
            $page1 = $db->supportTickets(25, 0);

            self::assertSame(2, $db->queryCount());
            self::assertCount(5, $dashboardSummary['statuses']);
            self::assertSame(30, $dashboardSummary['counts']['total']);
            self::assertSame(10, $dashboardSummary['counts']['closed']);
            self::assertSame(10, $dashboardSummary['counts']['in_progress']);
            self::assertCount(25, $page1);

            // Verificar que la primera fila es el ticket más reciente (#30) y trae JOINs + historial
            $latest = $page1[0];
            self::assertSame('Incidencia #30', $latest['title']);
            self::assertSame('42', (string) $latest['property_ident']);
            self::assertSame('Estación de trabajo', $latest['property_description']);
            self::assertSame('solicitante01', $latest['creator_username']);
            self::assertSame('finalizado', $latest['status_code']);
            self::assertCount(2, $latest['history']);
            self::assertSame('created', $latest['history'][0]['action_type']);
            self::assertSame('solicitante01', $latest['history'][0]['actor_username']);
            self::assertSame('comment', $latest['history'][1]['action_type']);
            self::assertSame('tecnico01', $latest['history'][1]['actor_username']);
            self::assertSame('Observación #30', $latest['history'][1]['observation']);

            // Segunda página devuelve los 5 restantes
            $page2 = $db->supportTickets(25, 25);
            self::assertCount(5, $page2);
            self::assertSame('Incidencia #1', $page2[4]['title']);
            self::assertSame(30, $db->supportTicketCount());
        } finally {
            $db->close();
        }
    }

    public function testRepositorySummariesCalculatePropertyAndMaintenanceMetrics(): void
    {
        $db = new DB(':memory:');

        try {
            $properties = array(
                array('id' => 1, 'grupo' => '2', 'subgrupo' => '0101', 'seccion' => '001', 'ident' => '0001', 'status' => 'BUENO', 'adscription' => 'Informática'),
                array('id' => 2, 'grupo' => '2', 'subgrupo' => '0005', 'seccion' => '003', 'ident' => '0002', 'status' => 'MALO', 'adscription' => ''),
                array('id' => 3, 'grupo' => '1', 'subgrupo' => '0001', 'seccion' => '001', 'ident' => '0003', 'status' => 'MUY MALO', 'adscription' => ''),
                array('id' => 4, 'grupo' => '1', 'subgrupo' => '0001', 'seccion' => '001', 'ident' => '0004', 'status' => 'REGULAR', 'adscription' => ''),
            );

            $statusSummary = $db->properties()->summarizeStatuses($properties);
            self::assertSame(array('total' => 4, 'good' => 1, 'attention' => 2), $statusSummary);

            $filterMeta = $db->properties()->buildFilterMetadata($properties, array(), false);
            self::assertSame(1, $filterMeta['local_adscription_count']);
            self::assertSame('informatica', $filterMeta['property_categories'][1]);
            self::assertSame('redes', $filterMeta['property_categories'][2]);
            self::assertSame('otros', $filterMeta['property_categories'][3]);

            $now = strtotime('2026-06-15 12:00:00');
            $maintenances = array(
                array('status' => 'iniciado', 'next_maintenance_date' => '2026-06-01'),
                array('status' => 'revision', 'next_maintenance_date' => '2026-07-01'),
                array('status' => 'finalizado', 'next_maintenance_date' => '2026-05-01'),
            );
            $maintSummary = $db->maintenances()->summarizeCounts($maintenances, $now);
            self::assertSame(
                array('total' => 3, 'iniciado' => 1, 'revision' => 1, 'finalizado' => 1, 'vencido' => 1),
                $maintSummary
            );
        } finally {
            $db->close();
        }
    }

    public function testMaintenanceTicketLookupReturnsOnlyTicketsForTheSelectedProperty(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));
            $creatorId = $db->create('users', array(
                'username' => 'ticketlookup',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $propertyId = $db->create('properties', array(
                'ident' => '0088',
                'description' => 'Impresora',
                'status' => 'BUENO',
            ));
            $otherPropertyId = $db->create('properties', array(
                'ident' => '0089',
                'description' => 'Escáner',
                'status' => 'BUENO',
            ));
            $status = $db->getTicketStatusByCode('iniciado');
            $ticketId = $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Revisar impresora',
                'description' => 'No imprime',
                'created_by' => $creatorId,
            ));
            $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Ticket eliminado',
                'description' => 'No debe mostrarse',
                'created_by' => $creatorId,
                'deleted_at' => '2026-01-01 10:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $otherPropertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Otro bien',
                'description' => 'Otro ticket',
                'created_by' => $creatorId,
            ));

            $tickets = $db->tickets()->maintenanceTicketsForProperty($propertyId);

            self::assertCount(1, $tickets);
            self::assertSame($ticketId, (int) $tickets[0]['id']);
            self::assertSame(88, $tickets[0]['property_ident']);
            self::assertSame('iniciado', $tickets[0]['status_code']);
        } finally {
            $db->close();
        }
    }

    public function testPropertyHistoryDoesNotIncludeSoftDeletedTickets(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));
            $creatorId = $db->create('users', array(
                'username' => 'historyowner',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $propertyId = $db->create('properties', array('ident' => '0090'));
            $otherPropertyId = $db->create('properties', array('ident' => '0091'));
            $status = $db->getTicketStatusByCode('iniciado');
            $activeTicketId = $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Ticket activo',
                'description' => 'Se conserva',
                'created_by' => $creatorId,
            ));
            $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Ticket eliminado',
                'description' => 'No debe aparecer',
                'created_by' => $creatorId,
                'deleted_at' => '2026-03-01 10:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $otherPropertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Ticket de otro bien',
                'description' => 'No corresponde al historial',
                'created_by' => $creatorId,
            ));

            $tickets = $db->tickets()->historyTicketsForProperty($propertyId);

            self::assertCount(1, $tickets);
            self::assertSame($activeTicketId, (int) $tickets[0]['id']);
            self::assertNull($tickets[0]['deleted_at']);
        } finally {
            $db->close();
        }
    }
}
