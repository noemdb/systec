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
}
