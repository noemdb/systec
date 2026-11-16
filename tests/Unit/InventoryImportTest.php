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
}
