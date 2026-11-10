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
}
