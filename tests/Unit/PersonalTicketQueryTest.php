<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class PersonalTicketQueryTest extends TestCase
{
    public function testPersonalTicketQueriesFilterPaginateAndScopeDetailsToOwner(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));
            $ownerId = $db->create('users', array(
                'username' => 'personal-owner',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $otherUserId = $db->create('users', array(
                'username' => 'other-owner',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $propertyId = $db->create('properties', array(
                'grupo' => '2',
                'subgrupo' => '0101',
                'seccion' => '001',
                'ident' => '0001',
                'description' => 'Laptop de prueba',
                'status' => 'BUENO',
            ));
            $otherPropertyId = $db->create('properties', array(
                'grupo' => '1',
                'subgrupo' => '0001',
                'seccion' => '001',
                'ident' => '0002',
                'description' => 'Monitor',
            ));
            $started = $db->getTicketStatusByCode('iniciado');
            $inProgress = $db->getTicketStatusByCode('en_progreso');

            $matchingTicketId = $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $inProgress['id'],
                'title' => 'Falla de teclado',
                'description' => 'No funciona',
                'created_by' => $ownerId,
                'created_at' => '2026-01-02 10:00:00',
                'updated_at' => '2026-01-03 10:00:00',
            ));
            $secondTicketId = $db->create('tickets', array(
                'property_id' => $otherPropertyId,
                'status_id' => (int) $started['id'],
                'title' => 'Revisión de monitor',
                'description' => 'Sin imagen',
                'created_by' => $ownerId,
                'created_at' => '2026-01-04 10:00:00',
                'updated_at' => '2026-01-04 10:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $started['id'],
                'title' => 'Ticket eliminado',
                'description' => 'No debe mostrarse',
                'created_by' => $ownerId,
                'created_at' => '2026-01-02 11:00:00',
                'updated_at' => '2026-01-02 11:00:00',
                'deleted_at' => '2026-01-05 10:00:00',
            ));
            $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $started['id'],
                'title' => 'Ticket de otra persona',
                'description' => 'Privado',
                'created_by' => $otherUserId,
                'created_at' => '2026-01-02 12:00:00',
                'updated_at' => '2026-01-02 12:00:00',
            ));
            $db->create('ticket_histories', array(
                'ticket_id' => $matchingTicketId,
                'actor_user_id' => $ownerId,
                'action_type' => 'created',
                'description' => 'Ticket creado.',
            ));

            self::assertSame(2, $db->personalTicketCount($ownerId));
            self::assertSame(1, $db->personalTicketCount($ownerId, array('category' => 'informatica')));
            self::assertSame(1, $db->personalTicketCount($ownerId, array('category' => 'otros')));
            self::assertSame(
                1,
                $db->personalTicketCount($ownerId, array('adscription_property_ids' => array($propertyId)))
            );
            self::assertSame(
                0,
                $db->personalTicketCount($ownerId, array('adscription_property_ids' => array()))
            );
            self::assertSame(
                1,
                $db->personalTicketCount($ownerId, array(
                    'search' => 'laptop',
                    'status' => 'en_progreso',
                    'date_from' => '2026-01-02',
                    'date_to' => '2026-01-02',
                ))
            );
            self::assertSame(
                2,
                array_sum(array_map(function ($row) {
                    return (int) $row['ticket_count'];
                }, $db->personalTicketStatusCounts($ownerId)))
            );
            self::assertSame(
                1,
                array_sum(array_map(function ($row) {
                    return (int) $row['ticket_count'];
                }, $db->personalTicketStatusCounts($ownerId, array('category' => 'informatica'))))
            );
            self::assertSame(
                array($secondTicketId, $matchingTicketId),
                array_merge(
                    array_column($db->personalTickets($ownerId, array(), 1, 0), 'id'),
                    array_column($db->personalTickets($ownerId, array(), 1, 1), 'id')
                )
            );
            self::assertSame(
                $matchingTicketId,
                (int) $db->personalTicketDetails($matchingTicketId, $ownerId)['id']
            );
            self::assertCount(1, $db->personalTicketDetails($matchingTicketId, $ownerId)['history']);
            self::assertNull($db->personalTicketDetails($matchingTicketId, $otherUserId));
        } finally {
            $db->close();
        }
    }
}
