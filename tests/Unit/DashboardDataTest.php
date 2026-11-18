<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class DashboardDataTest extends TestCase
{
    public function testMaintenanceCountsAreAggregatedByProperty(): void
    {
        $db = new DB(':memory:');

        try {
            $firstProperty = $db->create('properties', array('ident' => '0001'));
            $secondProperty = $db->create('properties', array('ident' => '0002'));
            $db->create('maintenances', array('property_id' => $firstProperty, 'status' => 'iniciado'));
            $db->create('maintenances', array('property_id' => $firstProperty, 'status' => 'finalizado'));
            $db->create('maintenances', array('property_id' => $secondProperty, 'status' => 'iniciado'));

            self::assertSame(1, $db->countRows('maintenances', array('status' => 'finalizado')));
            self::assertSame(array($firstProperty => 2, $secondProperty => 1), $db->maintenanceCountsByProperty());
        } finally {
            $db->close();
        }
    }

    public function testMaintenanceCountsAreEmptyWhenThereAreNoRows(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertSame(0, $db->countRows('maintenances', array('status' => 'finalizado')));
            self::assertSame(array(), $db->maintenanceCountsByProperty());
        } finally {
            $db->close();
        }
    }

    public function testTicketCountsAreAggregatedByPropertyAndExcludeDeletedTickets(): void
    {
        $db = new DB(':memory:');

        try {
            self::assertTrue($db->exec('ALTER TABLE tickets ADD COLUMN deleted_at DATETIME'));

            $firstProperty = $db->create('properties', array('ident' => '0001'));
            $secondProperty = $db->create('properties', array('ident' => '0002'));
            $userId = $db->create('users', array(
                'username' => 'operador',
                'password_hash' => 'hash',
            ));
            $status = $db->getTicketStatusByCode('iniciado');
            $ticket = array(
                'property_id' => $firstProperty,
                'status_id' => (int) $status['id'],
                'title' => 'Falla',
                'description' => 'Equipo sin respuesta',
                'created_by' => $userId,
            );
            $db->create('tickets', $ticket);
            $db->create('tickets', $ticket);
            $db->create('tickets', array_merge($ticket, array(
                'property_id' => $secondProperty,
            )));
            $db->create('tickets', array_merge($ticket, array(
                'deleted_at' => '2026-01-01 10:00:00',
            )));

            self::assertSame(
                array($firstProperty => 2, $secondProperty => 1),
                $db->ticketCountsByProperty()
            );
        } finally {
            $db->close();
        }
    }

    public function testInstitutionalAdscriptionsAreSavedForLocalFallback(): void
    {
        $db = new DB(':memory:');

        try {
            $propertyId = $db->create('properties', array(
                'ident' => '8',
                'adscription' => '',
            ));
            $properties = $db->index('properties', array(), 'id, ident, adscription');

            self::assertSame(
                1,
                $db->syncPropertyAdscriptions($properties, array(
                    '0008' => array('label' => 'Unidad de Informática'),
                ))
            );
            self::assertSame(
                'Unidad de Informática',
                $db->getFirstForId('properties', $propertyId)['adscription']
            );
            self::assertSame(
                0,
                $db->syncPropertyAdscriptions($db->index('properties', array(), 'id, ident, adscription'), array(
                    '0008' => array('label' => 'Unidad de Informática'),
                ))
            );
        } finally {
            $db->close();
        }
    }

    public function testInstitutionalPropertyAssignmentsAreSavedWithoutIdentifiers(): void
    {
        $db = new DB(':memory:');

        try {
            foreach (array('responsible_name', 'responsible_status', 'assigned_name', 'assigned_status') as $column) {
                self::assertTrue($db->exec('ALTER TABLE properties ADD COLUMN ' . $column . ' TEXT'));
            }

            $propertyId = $db->create('properties', array('ident' => '8'));
            $properties = $db->index('properties', array(), 'id, ident, responsible_name, responsible_status, assigned_name, assigned_status');
            $assignments = array(
                '0008' => array(
                    'responsible_name' => 'Ana Responsable',
                    'responsible_status' => 'ACTIVO',
                    'assigned_name' => 'Luis Asignado',
                    'assigned_status' => 'INACTIVO',
                ),
            );

            self::assertSame(1, $db->syncPropertyAssignments($properties, $assignments));
            self::assertSame(
                array(
                    'responsible_name' => 'Ana Responsable',
                    'responsible_status' => 'ACTIVO',
                    'assigned_name' => 'Luis Asignado',
                    'assigned_status' => 'INACTIVO',
                ),
                array_intersect_key(
                    $db->getFirstForId('properties', $propertyId),
                    array_flip(array('responsible_name', 'responsible_status', 'assigned_name', 'assigned_status'))
                )
            );
            self::assertSame(
                0,
                $db->syncPropertyAssignments(
                    $db->index('properties', array(), 'id, ident, responsible_name, responsible_status, assigned_name, assigned_status'),
                    $assignments
                )
            );
        } finally {
            $db->close();
        }
    }

    public function testIndexSupportsMultipleValidatedOrderColumns(): void
    {
        $db = new DB(':memory:');

        try {
            $firstId = $db->create('properties', array('ident' => '0001', 'description' => 'Equipo'));
            $secondId = $db->create('properties', array('ident' => '0002', 'description' => 'Equipo'));
            $thirdId = $db->create('properties', array('ident' => '0003', 'description' => 'Monitor'));

            $rows = $db->index('properties', array(), 'id, description', 'description ASC, id DESC');
            $sortedIds = array_map(function ($row) {
                return (int) $row['id'];
            }, $rows);

            self::assertSame(array($secondId, $firstId, $thirdId), $sortedIds);
        } finally {
            $db->close();
        }
    }

    public function testIndexRejectsUnknownColumnInMultipleOrderTerms(): void
    {
        $db = new DB(':memory:');

        try {
            $this->expectException(InvalidArgumentException::class);
            $db->index('properties', array(), '*', 'description ASC, unknown_column DESC');
        } finally {
            $db->close();
        }
    }

    public function testPropertyStatusListMatchesAcceptedValues(): void
    {
        $db = new DB(':memory:');

        try {
            $statuses = $db->getListPropertyStatus();
            self::assertCount(4, $statuses);
            self::assertArrayHasKey('bueno', $statuses);
            self::assertArrayHasKey('regular', $statuses);
            self::assertArrayHasKey('malo', $statuses);
            self::assertArrayHasKey('muy malo', $statuses);

            $propertyId = $db->create('properties', array('ident' => '0001', 'status' => 'BUENO'));
            $changes = $db->update('properties', $propertyId, array('status' => 'REGULAR'));
            self::assertSame(1, $changes);

            $updated = $db->getFirstForId('properties', $propertyId);
            self::assertSame('REGULAR', $updated['status']);
        } finally {
            $db->close();
        }
    }
}
