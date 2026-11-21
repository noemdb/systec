<?php

use PHPUnit\Framework\TestCase;

class InventoryImportTest extends TestCase
{
    /**
     * @dataProvider acceptedStatuses
     */
    public function testInventoryStatusIsNormalized(string $input, string $expected): void
    {
        self::assertSame($expected, inventory_normalize_status($input));
    }

    public function acceptedStatuses(): array
    {
        return array(
            array(' bueno ', 'BUENO'),
            array('Muy bueno', 'BUENO'),
            array('regular', 'REGULAR'),
            array('Malo', 'MALO'),
            array('muy malo', 'MUY MALO'),
        );
    }

    public function testInventoryStatusRejectsUnknownValues(): void
    {
        $this->expectException(CsvImportException::class);
        inventory_normalize_status('desconocido');
    }

    public function testDescriptionAppendsMissingAttributeAndAvoidsDuplicate(): void
    {
        self::assertSame(
            'Computador, MATERIAL: metal',
            inventory_append_description('Computador', 'MATERIAL', ' metal ')
        );
        self::assertSame(
            'Computador, Material: plástico',
            inventory_append_description('Computador, Material: plástico', 'MATERIAL', 'plástico')
        );
    }

    public function testInventoryAdscriptionUsesDepartmentAndHierarchicalCodes(): void
    {
        self::assertSame(
            array(
                'key' => '0001/0002/00000003',
                'label' => 'UNIDAD OPERATIVA',
                'direction_key' => '0001/0002',
                'direction_label' => 'DIRECCIÓN',
            ),
            inventory_adscription(array(
                'cod_dependencia' => '0001',
                'dependency_name' => 'DEPENDENCIA',
                'cod_direccion' => '0002',
                'direction_name' => 'DIRECCIÓN',
                'cod_departamento' => '00000003',
                'department_name' => 'UNIDAD OPERATIVA',
            ))
        );
    }

    public function testInventoryAdscriptionFallsBackToDirectionName(): void
    {
        self::assertSame(
            array(
                'key' => '0001/0002/',
                'label' => 'DIRECCIÓN',
                'direction_key' => '0001/0002',
                'direction_label' => 'DIRECCIÓN',
            ),
            inventory_adscription(array(
                'cod_dependencia' => '0001',
                'direction_name' => 'DIRECCIÓN',
                'cod_direccion' => '0002',
            ))
        );
    }

    public function testPropertyAssignmentFiltersUseOpaqueKeysAndReadableLabels(): void
    {
        $key = property_assignment_filter_key(' Ana Pérez ', ' activo ');

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
        self::assertSame($key, property_assignment_filter_key('Ana Pérez', 'ACTIVO'));
        self::assertNotSame($key, property_assignment_filter_key('Ana Pérez', 'INACTIVO'));
        self::assertSame('Ana Pérez · Activo', property_assignment_filter_label('Ana Pérez', 'ACTIVO'));
        self::assertSame('Sin ficha', property_assignment_filter_label('', 'SIN FICHA'));
    }

    public function testWriteLocalCacheStoresAdscriptionsAndPreservesAssignments(): void
    {
        $cacheDir = SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'cache';
        $identifiers = array('9991', '9992');
        $cachePath = $cacheDir . DIRECTORY_SEPARATOR
            . 'inventory-adscriptions-' . hash('sha256', implode("\n", $identifiers)) . '.json';
        $now = time();

        try {
            $written = legacy_postgres_write_local_cache(
                array('9991' => array('key' => 'k1', 'label' => 'Unidad A')),
                array('9991' => array('responsible_name' => 'Ana', 'responsible_status' => 'ACTIVO')),
                $identifiers,
                $now
            );
            self::assertTrue($written);
            self::assertFileExists($cachePath);

            // Re-escribir solo adscripciones debe preservar asignaciones existentes
            $updated = legacy_postgres_write_local_cache(
                array(
                    '9991' => array('key' => 'k1', 'label' => 'Unidad A'),
                    '9992' => array('key' => 'k2', 'label' => 'Unidad B'),
                ),
                array(),
                $identifiers,
                $now + 10
            );
            self::assertTrue($updated);

            $decoded = json_decode((string) file_get_contents($cachePath), true);
            self::assertIsArray($decoded);
            self::assertFalse($decoded['stale']);
            self::assertSame($now + 10, $decoded['cached_at']);
            self::assertCount(2, $decoded['adscriptions']);
            self::assertCount(1, $decoded['assignments']);
        } finally {
            @unlink($cachePath);
            @unlink($cachePath . '.lock');
        }
    }
}
