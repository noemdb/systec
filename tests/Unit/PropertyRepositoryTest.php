<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class PropertyRepositoryTest extends TestCase
{
    public function testFilterMetadataDeduplicatesInstitutionalAndLocalAdscriptionLabels(): void
    {
        $db = new DB(':memory:');

        try {
            $properties = array(
                array('id' => 1, 'ident' => '0001', 'adscription' => '  DCOPPMED  '),
                array('id' => 2, 'ident' => '0002', 'adscription' => 'DCOPPMED'),
                array('id' => 3, 'ident' => '0003', 'adscription' => 'Unidad solo local'),
            );
            $legacyAdscriptions = array(
                '0002' => array('key' => '0001/0004/00000004', 'label' => 'DCOPPMED'),
            );

            $metadata = $db->properties()->buildFilterMetadata($properties, $legacyAdscriptions);

            self::assertCount(2, $metadata['adscription_options']);
            self::assertArrayHasKey('0001/0004/00000004', $metadata['adscription_options']);
            self::assertSame('DCOPPMED', $metadata['adscription_options']['0001/0004/00000004']);
            self::assertSame(
                array('key' => '0001/0004/00000004', 'label' => 'DCOPPMED'),
                $metadata['adscriptions_by_property_id'][1]
            );
            self::assertSame(
                $metadata['adscriptions_by_property_id'][1],
                $metadata['adscriptions_by_property_id'][2]
            );
        } finally {
            $db->close();
        }
    }
}
