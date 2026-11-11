<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class TicketHistoryTest extends TestCase
{
    public function testTicketHistoryIncludesUsernameOfTheActor(): void
    {
        $db = new DB(':memory:');

        try {
            $userId = $db->create('users', array(
                'username' => 'tecnico',
                'password_hash' => 'hash',
                'role' => 'tecnico',
            ));
            $propertyId = $db->create('properties', array('ident' => '0001'));
            $status = $db->getTicketStatusByCode('iniciado');
            $ticketId = $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Falla',
                'description' => 'No enciende',
                'created_by' => $userId,
            ));
            $db->create('ticket_histories', array(
                'ticket_id' => $ticketId,
                'actor_user_id' => $userId,
                'action_type' => 'comment',
                'description' => 'Revisando el equipo',
            ));

            $history = $db->ticketHistoryWithActors($ticketId);

            self::assertCount(1, $history);
            self::assertSame('tecnico', $history[0]['actor_username']);
        } finally {
            $db->close();
        }
    }

    public function testSoftDeletingTicketPreservesHistoryAndHidesTicketFromActiveList(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));
            $userId = $db->create('users', array(
                'username' => 'operador',
                'password_hash' => 'hash',
                'role' => 'operador',
            ));
            $propertyId = $db->create('properties', array('ident' => '0002'));
            $status = $db->getTicketStatusByCode('iniciado');
            $ticketId = $db->create('tickets', array(
                'property_id' => $propertyId,
                'status_id' => (int) $status['id'],
                'title' => 'Falla',
                'description' => 'No enciende',
                'created_by' => $userId,
            ));
            $db->create('ticket_histories', array(
                'ticket_id' => $ticketId,
                'actor_user_id' => $userId,
                'action_type' => 'created',
                'description' => 'Ticket registrado.',
            ));

            self::assertTrue($db->softDeleteTicket($ticketId, $userId, '2099-11-11 12:00:00'));
            self::assertFalse($db->softDeleteTicket($ticketId, $userId, '2099-11-11 12:01:00'));
            self::assertSame(
                array(),
                $db->index('tickets', array(array('deleted_at', 'IS', null)))
            );
            self::assertSame(2, $db->countRows('ticket_histories', array('ticket_id' => $ticketId)));
            $history = $db->ticketHistoryWithActors($ticketId);
            self::assertSame('deleted', $history[1]['action_type']);
        } finally {
            $db->close();
        }
    }
}
