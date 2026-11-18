<?php

/**
 * Repositorio de mantenimientos (maintenances).
 *
 * Centraliza las consultas agregadas, reportes de bienes dañados,
 * catálogos de tipos/estados/técnicos y cálculos de duración.
 */
class MaintenanceRepository
{
    /** @var DB */
    private $db;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    public function indexWithMaintenances($fields = 'properties.*', $sort = null): array
    {
        if ($fields !== '*' && $fields !== 'properties.*') {
            throw new InvalidArgumentException('Campos no permitidos para el reporte');
        }

        $query = "
            SELECT DISTINCT $fields
            FROM properties
            INNER JOIN maintenances ON properties.id = maintenances.property_id
            WHERE TRIM(maintenances.notes) != ''
            AND maintenances.notes LIKE '%desincorporación%'
            AND (properties.status = 'MALO' OR properties.status = 'MUY MALO')
        ";

        $query .= $this->db->buildOrderClause('maintenances', $sort);

        return $this->db->select($query);
    }

    public function maintenanceCountsByProperty(): array
    {
        $rows = $this->db->fetchAllQuery(
            'SELECT property_id, COUNT(*) AS maintenance_count
             FROM maintenances
             GROUP BY property_id'
        );
        $counts = array();

        foreach ($rows as $row) {
            $counts[(int) $row['property_id']] = (int) $row['maintenance_count'];
        }

        return $counts;
    }

    public function getListMaintenanceStatus(): array
    {
        return array(
            'iniciado' => 'Iniciado',
            'revision' => 'Revisión',
            'finalizado' => 'Finalizado',
        );
    }

    public function getListMaintenanceType(): array
    {
        return array(
            'preventivo' => 'Preventivo',
            'correctivo' => 'Correctivo',
            'predictivo' => 'Predictivo',
            'evaluativo' => 'Evaluativo',
        );
    }

    public function getListMaintenanceTechnician(): array
    {
        $technicians = array(
            'jmedina' => 'jmedina',
            'ndominguez' => 'ndominguez',
        );

        $rows = $this->db->fetchAllQuery(
            "SELECT username, active
             FROM users
             WHERE role = 'tecnico'
             ORDER BY username ASC"
        );

        foreach ($rows as $row) {
            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            if ((int) ($row['active'] ?? 0) === 1) {
                $technicians[$username] = $username;
            } else {
                unset($technicians[$username]);
            }
        }

        ksort($technicians, SORT_NATURAL | SORT_FLAG_CASE);

        return $technicians;
    }

    public function summarizeCounts(array $maintenances, ?int $now = null): array
    {
        $referenceTime = $now !== null ? $now : time();
        $counts = array(
            'total' => count($maintenances),
            'iniciado' => 0,
            'revision' => 0,
            'finalizado' => 0,
            'vencido' => 0,
        );

        foreach ($maintenances as $maintenance) {
            $status = mb_strtolower(trim((string) ($maintenance['status'] ?? '')));
            if (isset($counts[$status])) {
                $counts[$status]++;
            }

            $nextDate = trim((string) ($maintenance['next_maintenance_date'] ?? ''));
            $nextTimestamp = ($nextDate !== '') ? strtotime($nextDate) : false;
            if ($status !== 'finalizado' && $nextTimestamp !== false && $nextTimestamp < $referenceTime) {
                $counts['vencido']++;
            }
        }

        return $counts;
    }

    public function restarFechasEnHoras(DateTime $fecha1, DateTime $fecha2): int
    {
        return (int) round(abs($fecha1->getTimestamp() - $fecha2->getTimestamp()) / 3600);
    }
}
