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

    /**
     * Precarga en una sola consulta los mantenimientos de un conjunto de bienes, agrupados por property_id.
     */
    public function maintenancesGroupedByPropertyIds(array $propertyIds): array
    {
        $ids = array();
        foreach ($propertyIds as $rawId) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT);
            if ($id !== false && $id !== null && $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }

        if ($ids === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach (array_values($ids) as $index => $id) {
            $param = ':pid' . $index;
            $placeholders[] = $param;
            $params[$param] = $id;
        }

        $rows = $this->db->fetchAllQuery(
            'SELECT *
             FROM maintenances
             WHERE property_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY date ASC, id ASC',
            $params
        );

        $grouped = array();
        foreach ($rows as $row) {
            $pid = (int) $row['property_id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = array();
            }
            $grouped[$pid][] = $row;
        }

        return $grouped;
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

    public function damagedReportCount(): int
    {
        $rows = $this->db->fetchAllQuery(
            "SELECT COUNT(DISTINCT properties.id) AS total
             FROM properties
             INNER JOIN maintenances ON properties.id = maintenances.property_id
             WHERE TRIM(COALESCE(maintenances.notes, '')) != ''
               AND maintenances.notes LIKE '%desincorporación%'
               AND (properties.status = 'MALO' OR properties.status = 'MUY MALO')"
        );

        return (int) ($rows[0]['total'] ?? 0);
    }

    public function dashboardSummaryCounts(?int $now = null): array
    {
        $referenceTime = $now !== null ? $now : time();
        $nowIso = date('Y-m-d H:i:s', $referenceTime);

        $rows = $this->db->fetchAllQuery(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) = 'iniciado' THEN 1 ELSE 0 END), 0) AS iniciado,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) = 'revision' THEN 1 ELSE 0 END), 0) AS revision,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) = 'finalizado' THEN 1 ELSE 0 END), 0) AS finalizado,
                COALESCE(SUM(CASE
                    WHEN LOWER(TRIM(COALESCE(status, ''))) != 'finalizado'
                     AND TRIM(COALESCE(next_maintenance_date, '')) != ''
                     AND datetime(TRIM(next_maintenance_date)) IS NOT NULL
                     AND datetime(TRIM(next_maintenance_date)) < datetime(:now_iso)
                    THEN 1 ELSE 0 END), 0) AS vencido
             FROM maintenances",
            array(':now_iso' => $nowIso)
        );

        $row = $rows ? $rows[0] : array();

        return array(
            'total' => (int) ($row['total'] ?? 0),
            'iniciado' => (int) ($row['iniciado'] ?? 0),
            'revision' => (int) ($row['revision'] ?? 0),
            'finalizado' => (int) ($row['finalizado'] ?? 0),
            'vencido' => (int) ($row['vencido'] ?? 0),
        );
    }

    public function urgentMaintenances(int $limit = 5, ?int $now = null, int $upcomingDays = 7): array
    {
        $limit = max(1, min(50, $limit));
        $referenceTime = $now !== null ? $now : time();
        $nowIso = date('Y-m-d H:i:s', $referenceTime);
        $upcomingIso = date('Y-m-d H:i:s', $referenceTime + max(1, $upcomingDays) * 86400);

        return $this->db->fetchAllQuery(
            "SELECT
                m.id,
                m.property_id,
                m.code,
                m.type,
                m.description,
                m.status,
                m.technician,
                m.next_maintenance_date,
                p.ident AS property_ident,
                p.description AS property_description,
                CASE
                    WHEN datetime(TRIM(m.next_maintenance_date)) < datetime(:now_iso) THEN 1
                    ELSE 0
                END AS is_overdue
             FROM maintenances m
             LEFT JOIN properties p ON p.id = m.property_id
             WHERE LOWER(TRIM(COALESCE(m.status, ''))) != 'finalizado'
               AND TRIM(COALESCE(m.next_maintenance_date, '')) != ''
               AND datetime(TRIM(m.next_maintenance_date)) IS NOT NULL
               AND datetime(TRIM(m.next_maintenance_date)) <= datetime(:upcoming_iso)
             ORDER BY datetime(TRIM(m.next_maintenance_date)) ASC, m.id DESC
             LIMIT " . $limit,
            array(
                ':now_iso' => $nowIso,
                ':upcoming_iso' => $upcomingIso,
            )
        );
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
