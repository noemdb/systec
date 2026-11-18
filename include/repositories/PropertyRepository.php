<?php

/**
 * Repositorio de bienes muebles (properties).
 *
 * Centraliza las consultas, sincronizaciones y catálogos de inventario
 * para desacoplar la lógica de acceso a datos de las páginas y de DB.
 */
class PropertyRepository
{
    /** @var DB */
    private $db;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    public function createOrUpdate($dataCsv, bool $preserveExistingStatus = false): string
    {
        if (!is_array($dataCsv) || count($dataCsv) < 11) {
            throw new InvalidArgumentException('Fila CSV incompleta');
        }

        $ident = str_pad(trim((string) $dataCsv[3]), 4, '0', STR_PAD_LEFT);
        $property = $this->db->getFirstForConditions('properties', array('ident' => $ident));

        if (!$property) {
            $modelParts = array_filter(array_map(function ($value) {
                return trim((string) $value);
            }, array($dataCsv[5], $dataCsv[6])), function ($value) {
                return $value !== '';
            });
            $data = array(
                'grupo' => $dataCsv[0],
                'subgrupo' => str_pad((string) $dataCsv[1], 4, '0', STR_PAD_LEFT),
                'seccion' => str_pad((string) $dataCsv[2], 3, '0', STR_PAD_LEFT),
                'ident' => $ident,
                'description' => $dataCsv[4],
                'model' => implode(' - ', $modelParts),
                'serial' => $dataCsv[7],
                'color' => $dataCsv[9],
                'status' => $dataCsv[10],
            );
            $this->db->create('properties', $data);
            return 'inserted';
        }

        if ($preserveExistingStatus
            && (string) $property['status'] !== trim((string) $dataCsv[10])) {
            return 'preserved';
        }
        if ((string) $property['status'] === trim((string) $dataCsv[10])) {
            return 'unchanged';
        }
        $this->db->update('properties', $property['id'], array('status' => $dataCsv[10]));
        return 'updated';
    }

    public function syncPropertyAdscriptions(array $properties, array $adscriptionsByIdent): int
    {
        $updates = array();
        foreach ($properties as $property) {
            $ident = str_pad(trim((string) ($property['ident'] ?? '')), 4, '0', STR_PAD_LEFT);
            $assignment = $adscriptionsByIdent[$ident] ?? null;
            if (!is_array($assignment) || !isset($assignment['label']) || !is_string($assignment['label'])) {
                continue;
            }

            $label = trim($assignment['label']);
            if ($label === '' || $label === trim((string) ($property['adscription'] ?? ''))) {
                continue;
            }

            $propertyId = (int) ($property['id'] ?? 0);
            if ($propertyId < 1) {
                throw new InvalidArgumentException('No se puede sincronizar una adscripción sin ID de bien.');
            }
            $updates[$propertyId] = $label;
        }

        if ($updates === array()) {
            return 0;
        }
        if (!$this->db->exec('BEGIN IMMEDIATE')) {
            throw new RuntimeException('No se pudo iniciar la sincronización de adscripciones: ' . $this->db->lastErrorMsg());
        }

        try {
            foreach ($updates as $propertyId => $label) {
                if ($this->db->update('properties', $propertyId, array('adscription' => $label)) !== 1) {
                    throw new RuntimeException('No se pudo guardar la adscripción del bien #' . $propertyId . '.');
                }
            }
            if (!$this->db->exec('COMMIT')) {
                throw new RuntimeException('No se pudo confirmar la sincronización de adscripciones: ' . $this->db->lastErrorMsg());
            }
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }

        return count($updates);
    }

    public function syncPropertyAssignments(array $properties, array $assignmentsByIdent): int
    {
        $fields = array('responsible_name', 'responsible_status', 'assigned_name', 'assigned_status');
        $updates = array();

        foreach ($properties as $property) {
            $ident = str_pad(trim((string) ($property['ident'] ?? '')), 4, '0', STR_PAD_LEFT);
            if (!isset($assignmentsByIdent[$ident]) || !is_array($assignmentsByIdent[$ident])) {
                continue;
            }

            $propertyId = (int) ($property['id'] ?? 0);
            if ($propertyId < 1) {
                throw new InvalidArgumentException('No se puede sincronizar una asignación sin ID de bien.');
            }

            foreach ($fields as $field) {
                $value = $assignmentsByIdent[$ident][$field] ?? null;
                if (!is_string($value)) {
                    throw new InvalidArgumentException('La asignación del bien no contiene el campo esperado: ' . $field);
                }

                $value = trim($value);
                if ($value !== trim((string) ($property[$field] ?? ''))) {
                    $updates[$propertyId][$field] = $value;
                }
            }
        }

        if ($updates === array()) {
            return 0;
        }
        if (!$this->db->exec('BEGIN IMMEDIATE')) {
            throw new RuntimeException('No se pudo iniciar la sincronización de asignaciones: ' . $this->db->lastErrorMsg());
        }

        try {
            foreach ($updates as $propertyId => $values) {
                if ($this->db->update('properties', $propertyId, $values) !== 1) {
                    throw new RuntimeException('No se pudo guardar la asignación del bien #' . $propertyId . '.');
                }
            }
            if (!$this->db->exec('COMMIT')) {
                throw new RuntimeException('No se pudo confirmar la sincronización de asignaciones: ' . $this->db->lastErrorMsg());
            }
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }

        return count($updates);
    }

    public function getListProperties(string $table = 'properties'): array
    {
        $rows = $this->db->index($table, array(), '*', 'id ASC');
        $list = array();

        foreach ($rows as $row) {
            $list[$row['id']] = property_code($row);
        }

        return $list;
    }

    public function getCodeId($id): ?string
    {
        $row = $this->db->getFirstForId('properties', $id);
        if (!$row) {
            return null;
        }

        return property_code($row);
    }

    public function getListPropertyStatus(): array
    {
        return array(
            'bueno' => 'Bueno',
            'regular' => 'Regular',
            'malo' => 'Malo',
            'muy malo' => 'Muy malo',
        );
    }

    public function summarizeStatuses(array $properties): array
    {
        $summary = array(
            'total' => count($properties),
            'good' => 0,
            'attention' => 0,
        );

        foreach ($properties as $property) {
            $status = mb_strtoupper(trim((string) ($property['status'] ?? '')));
            if ($status === 'BUENO') {
                $summary['good']++;
            }
            if ($status === 'MALO' || $status === 'MUY MALO') {
                $summary['attention']++;
            }
        }

        return $summary;
    }

    public function buildFilterMetadata(
        array $properties,
        array $legacyAdscriptionsByIdent,
        bool $includeAssignments = false
    ): array {
        $adscriptionsByPropertyId = array();
        $adscriptionOptions = array();
        $assignmentFilterOptions = array('responsible' => array(), 'assigned' => array());
        $assignmentFilterKeysByPropertyId = array();
        $propertyCategories = array();
        $propertiesById = array();
        $localAdscriptionCount = 0;

        foreach ($properties as $property) {
            $propertyId = (int) $property['id'];
            $propertiesById[$propertyId] = $property;
            $propertyCategories[$propertyId] = property_category($property);
            if (trim((string) ($property['adscription'] ?? '')) !== '') {
                $localAdscriptionCount++;
            }

            $adscription = property_adscription_assignment($property, $legacyAdscriptionsByIdent);
            $adscriptionsByPropertyId[$propertyId] = $adscription;
            $adscriptionOptions[$adscription['key']] = $adscription['label'];

            if ($includeAssignments) {
                foreach (array('responsible' => 'responsible', 'assigned' => 'assigned') as $filterKey => $fieldPrefix) {
                    $name = trim((string) ($property[$fieldPrefix . '_name'] ?? ''));
                    $status = trim((string) ($property[$fieldPrefix . '_status'] ?? ''));
                    $key = property_assignment_filter_key($name, $status);
                    $assignmentFilterOptions[$filterKey][$key] = property_assignment_filter_label($name, $status);
                    $assignmentFilterKeysByPropertyId[$propertyId][$filterKey] = $key;
                }
            }
        }

        asort($adscriptionOptions, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($assignmentFilterOptions as &$options) {
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($options);

        return array(
            'adscriptions_by_property_id' => $adscriptionsByPropertyId,
            'adscription_options' => $adscriptionOptions,
            'assignment_filter_options' => $assignmentFilterOptions,
            'assignment_filter_keys_by_property_id' => $assignmentFilterKeysByPropertyId,
            'property_categories' => $propertyCategories,
            'properties_by_id' => $propertiesById,
            'local_adscription_count' => $localAdscriptionCount,
        );
    }
}
