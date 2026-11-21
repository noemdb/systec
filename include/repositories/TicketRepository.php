<?php

/**
 * Repositorio de tickets e historiales (tickets, ticket_status, ticket_histories).
 *
 * Centraliza las consultas agregadas y paginadas tanto del área personal
 * como de la bandeja de soporte (PERF-02: JOIN único + paginación sin N+1).
 */
class TicketRepository
{
    /** @var DB */
    private $db;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    public function ticketCountsByProperty(): array
    {
        $rows = $this->db->fetchAllQuery(
            'SELECT property_id, COUNT(*) AS ticket_count
             FROM tickets
             WHERE deleted_at IS NULL
             GROUP BY property_id'
        );
        $counts = array();

        foreach ($rows as $row) {
            $counts[(int) $row['property_id']] = (int) $row['ticket_count'];
        }

        return $counts;
    }

    public function maintenanceTicketsForProperty(int $propertyId): array
    {
        if ($propertyId < 1) {
            throw new InvalidArgumentException('Identificador de bien inválido.');
        }

        return $this->db->fetchAllQuery(
            'SELECT t.id, t.property_id, t.title, t.description, t.observation,
                    t.created_at, t.updated_at, s.code AS status_code, s.name AS status_name,
                    p.ident AS property_ident, p.grupo AS property_grupo,
                    p.subgrupo AS property_subgrupo, p.seccion AS property_seccion,
                    p.description AS property_description
             FROM tickets t
             LEFT JOIN ticket_status s ON s.id = t.status_id
             LEFT JOIN properties p ON p.id = t.property_id
             WHERE t.property_id = :property_id AND t.deleted_at IS NULL
             ORDER BY t.updated_at DESC, t.id DESC',
            array(':property_id' => $propertyId)
        );
    }

    public function historyTicketsForProperty(int $propertyId): array
    {
        if ($propertyId < 1) {
            throw new InvalidArgumentException('Identificador de bien inválido.');
        }

        return $this->db->index(
            'tickets',
            array(
                array('property_id', '=', $propertyId),
                array('deleted_at', 'IS', null),
            ),
            '*',
            'created_at ASC'
        );
    }

    public function getTicketStatuses(): array
    {
        $rows = $this->db->index('ticket_status', array('active' => 1), '*', 'sort_order ASC');
        $statuses = array();

        foreach ($rows as $row) {
            $statuses[(int) $row['id']] = $row;
        }

        return $statuses;
    }

    public function getTicketStatusByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $row = $this->db->getFirstForConditions(
            'ticket_status',
            array(array('code', '=', $code), array('active', '=', 1))
        );
        return $row ?: null;
    }

    public function ticketHistoryWithActors(int $ticketId): array
    {
        if ($ticketId < 1) {
            return array();
        }

        return $this->db->fetchAllQuery(
            'SELECT ticket_histories.*, users.username AS actor_username
             FROM ticket_histories
             LEFT JOIN users ON users.id = ticket_histories.actor_user_id
             WHERE ticket_histories.ticket_id = :ticket_id
             ORDER BY ticket_histories.created_at ASC, ticket_histories.id ASC',
            array(':ticket_id' => $ticketId)
        );
    }

    /**
     * PERF-02: Obtiene en una sola consulta los estados activos y el conteo de tickets
     * no eliminados para cada estado en la bandeja de soporte.
     */
    public function supportTicketStatusCounts(?int $createdBy = null): array
    {
        $params = array();
        $joinConditions = array('t.status_id = s.id', 't.deleted_at IS NULL');
        if ($createdBy !== null) {
            if ($createdBy < 1) {
                throw new InvalidArgumentException('Identificador de usuario inválido.');
            }
            $joinConditions[] = 't.created_by = :created_by';
            $params[':created_by'] = $createdBy;
        }

        return $this->db->fetchAllQuery(
            'SELECT s.id, s.code, s.name, s.sort_order, s.is_terminal, s.active,
                    COUNT(t.id) AS ticket_count
             FROM ticket_status s
             LEFT JOIN tickets t ON ' . implode(' AND ', $joinConditions) . '
             WHERE s.active = 1
             GROUP BY s.id, s.code, s.name, s.sort_order, s.is_terminal, s.active
             ORDER BY s.sort_order ASC',
            $params
        );
    }

    /**
     * PERF-02: Construye el catálogo indexado de estados y los totales por indicador
     * a partir de la consulta única supportTicketStatusCounts().
     */
    public function supportTicketDashboardSummary(?int $createdBy = null): array
    {
        $statusRows = $this->supportTicketStatusCounts($createdBy);
        $statuses = array();
        $summaryCounts = array(
            'total' => 0,
            'in_progress' => 0,
            'delayed' => 0,
            'waiting' => 0,
            'closed' => 0,
        );
        $summaryKeyByStatus = array(
            'en_progreso' => 'in_progress',
            'con_retraso' => 'delayed',
            'en_espera' => 'waiting',
            'finalizado' => 'closed',
        );
        foreach ($statusRows as $statusRow) {
            $statusId = (int) $statusRow['id'];
            $count = (int) $statusRow['ticket_count'];
            $statuses[$statusId] = array(
                'id' => $statusId,
                'code' => (string) $statusRow['code'],
                'name' => (string) $statusRow['name'],
                'sort_order' => (int) $statusRow['sort_order'],
                'is_terminal' => (int) $statusRow['is_terminal'],
                'active' => (int) $statusRow['active'],
            );
            $summaryCounts['total'] += $count;
            $summaryKey = isset($summaryKeyByStatus[(string) $statusRow['code']])
                ? $summaryKeyByStatus[(string) $statusRow['code']]
                : null;
            if ($summaryKey !== null) {
                $summaryCounts[$summaryKey] += $count;
            }
        }

        return array(
            'statuses' => $statuses,
            'counts' => $summaryCounts,
        );
    }

    /**
     * Obtiene los tickets abiertos más prioritarios (con retraso primero).
     */
    public function urgentOpenTickets(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));

        return $this->db->fetchAllQuery(
            "SELECT
                t.id,
                t.property_id,
                t.title,
                t.updated_at,
                s.code AS status_code,
                s.name AS status_name,
                p.ident AS property_ident,
                p.description AS property_description
             FROM tickets t
             INNER JOIN ticket_status s ON s.id = t.status_id
             LEFT JOIN properties p ON p.id = t.property_id
             WHERE t.deleted_at IS NULL
               AND s.is_terminal = 0
             ORDER BY
                CASE WHEN s.code = 'con_retraso' THEN 0 ELSE 1 END ASC,
                t.updated_at DESC,
                t.id DESC
             LIMIT " . $limit
        );
    }

    /**
     * PERF-02: Conteo total de tickets activos para soporte.
     */
    public function supportTicketCount(?int $createdBy = null): int
    {
        $params = array();
        $clauses = array('t.deleted_at IS NULL');
        if ($createdBy !== null) {
            if ($createdBy < 1) {
                throw new InvalidArgumentException('Identificador de usuario inválido.');
            }
            $clauses[] = 't.created_by = :created_by';
            $params[':created_by'] = $createdBy;
        }

        $rows = $this->db->fetchAllQuery(
            'SELECT COUNT(*) AS ticket_count
             FROM tickets t
             WHERE ' . implode(' AND ', $clauses),
            $params
        );

        return (int) ($rows[0]['ticket_count'] ?? 0);
    }

    /**
     * PERF-02: Lista paginada de tickets para soporte en una única consulta JOIN
     * que incluye bien, creador, estado e historial con actores (sin N+1).
     */
    public function supportTickets(int $limit, int $offset, ?int $createdBy = null): array
    {
        if ($limit < 1 || $offset < 0) {
            throw new InvalidArgumentException('Paginación inválida.');
        }

        $params = array(
            ':limit' => $limit,
            ':offset' => $offset,
        );
        $clauses = array('t.deleted_at IS NULL');
        if ($createdBy !== null) {
            if ($createdBy < 1) {
                throw new InvalidArgumentException('Identificador de usuario inválido.');
            }
            $clauses[] = 't.created_by = :created_by';
            $params[':created_by'] = $createdBy;
        }

        $rows = $this->db->fetchAllQuery(
            "SELECT t.id, t.property_id, t.status_id, t.title, t.description, t.observation,
                    t.created_by, t.assigned_to, t.created_at, t.updated_at, t.closed_at,
                    p.ident AS property_ident, p.description AS property_description,
                    p.status AS property_status, p.grupo AS property_grupo,
                    p.subgrupo AS property_subgrupo, p.seccion AS property_seccion,
                    u.username AS creator_username,
                    s.code AS status_code, s.name AS status_name,
                    (
                        SELECT json_group_array(
                            json_object(
                                'created_at', COALESCE(th.created_at, ''),
                                'action_type', COALESCE(th.action_type, ''),
                                'description', COALESCE(th.description, ''),
                                'observation', COALESCE(th.observation, ''),
                                'actor_username', COALESCE(th.actor_username, '')
                            )
                        )
                        FROM (
                            SELECT th_inner.created_at, th_inner.action_type,
                                   th_inner.description, th_inner.observation,
                                   au.username AS actor_username
                            FROM ticket_histories th_inner
                            LEFT JOIN users au ON au.id = th_inner.actor_user_id
                            WHERE th_inner.ticket_id = t.id
                            ORDER BY th_inner.created_at ASC, th_inner.id ASC
                        ) th
                    ) AS history_json
             FROM tickets t
             LEFT JOIN properties p ON p.id = t.property_id
             LEFT JOIN users u ON u.id = t.created_by
             LEFT JOIN ticket_status s ON s.id = t.status_id
             WHERE " . implode(' AND ', $clauses) . '
             ORDER BY t.updated_at DESC, t.id DESC
             LIMIT :limit OFFSET :offset',
            $params
        );

        foreach ($rows as &$row) {
            $decoded = isset($row['history_json'])
                ? json_decode((string) $row['history_json'], true)
                : array();
            $row['history'] = is_array($decoded) ? $decoded : array();
            unset($row['history_json']);
        }
        unset($row);

        return $rows;
    }

    private function personalTicketWhere(int $userId, array $filters, array &$params): string
    {
        $params = array(':created_by' => $userId);
        $clauses = array('t.created_by = :created_by', 't.deleted_at IS NULL');

        if (!empty($filters['search'])) {
            $clauses[] = "INSTR(LOWER(COALESCE(t.title, '') || ' ' || COALESCE(p.ident, '') || ' ' || COALESCE(p.description, '')), LOWER(:search)) > 0";
            $params[':search'] = trim((string) $filters['search']);
        }
        if (!empty($filters['status'])) {
            $clauses[] = 's.code = :status';
            $params[':status'] = trim((string) $filters['status']);
        }
        if (!empty($filters['category'])) {
            $groupCode = "CAST(COALESCE(p.grupo, '') AS INTEGER)";
            $subgroupCode = "printf('%04d', CAST(COALESCE(p.subgrupo, '') AS INTEGER))";
            $sectionCode = "printf('%03d', CAST(COALESCE(p.seccion, '') AS INTEGER))";
            $informatics = "({$groupCode} = 2 AND ({$subgroupCode} = '0101' OR ({$subgroupCode} = '0012' AND {$sectionCode} = '005')))";
            $networks = "({$groupCode} = 2 AND {$subgroupCode} = '0005' AND {$sectionCode} IN ('003', '008', '037'))";
            if ($filters['category'] === 'informatica') {
                $clauses[] = $informatics;
            } elseif ($filters['category'] === 'redes') {
                $clauses[] = $networks;
            } elseif ($filters['category'] === 'otros') {
                $clauses[] = 'NOT (' . $informatics . ' OR ' . $networks . ')';
            } else {
                throw new InvalidArgumentException('Categoría de bien inválida.');
            }
        }
        if (array_key_exists('adscription_property_ids', $filters)) {
            $propertyIds = array_values(array_unique(array_filter(array_map('intval', (array) $filters['adscription_property_ids']), function ($id) {
                return $id > 0;
            })));
            if (!$propertyIds) {
                $clauses[] = '0 = 1';
            } else {
                $encodedIds = json_encode($propertyIds);
                if ($encodedIds === false) {
                    throw new RuntimeException('No se pudieron preparar los bienes de la adscripción seleccionada.');
                }
                $clauses[] = 't.property_id IN (SELECT CAST(value AS INTEGER) FROM json_each(:adscription_property_ids))';
                $params[':adscription_property_ids'] = $encodedIds;
            }
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = "t.created_at >= :date_from || ' 00:00:00'";
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = "t.created_at < DATE(:date_to, '+1 day')";
            $params[':date_to'] = (string) $filters['date_to'];
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    public function personalTicketStatusCounts(int $userId, array $filters = array()): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Identificador de usuario inválido.');
        }

        unset($filters['status']);
        $params = array();
        $where = $this->personalTicketWhere($userId, $filters, $params);
        return $this->db->fetchAllQuery(
            'SELECT s.code, COUNT(*) AS ticket_count
            FROM tickets t
            LEFT JOIN properties p ON p.id = t.property_id
            LEFT JOIN ticket_status s ON s.id = t.status_id
            ' . $where . '
            GROUP BY s.code',
            $params
        );
    }

    public function personalTicketCount(int $userId, array $filters = array()): int
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Identificador de usuario inválido.');
        }

        $params = array();
        $where = $this->personalTicketWhere($userId, $filters, $params);
        $rows = $this->db->fetchAllQuery(
            'SELECT COUNT(*) AS ticket_count
            FROM tickets t
            LEFT JOIN properties p ON p.id = t.property_id
            LEFT JOIN ticket_status s ON s.id = t.status_id' . $where,
            $params
        );

        return (int) ($rows[0]['ticket_count'] ?? 0);
    }

    public function personalTickets(int $userId, array $filters, int $limit, int $offset): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Identificador de usuario inválido.');
        }
        if ($limit < 1 || $offset < 0) {
            throw new InvalidArgumentException('Paginación inválida.');
        }

        $params = array();
        $where = $this->personalTicketWhere($userId, $filters, $params);
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        return $this->db->fetchAllQuery(
            'SELECT t.id, t.property_id, t.status_id, t.title, t.description, t.observation,
                   t.created_by, t.assigned_to, t.created_at, t.updated_at, t.closed_at,
                   p.ident AS property_ident, p.description AS property_description,
                   p.status AS property_status, p.grupo AS property_grupo,
                   p.subgrupo AS property_subgrupo, p.seccion AS property_seccion,
                   s.code AS status_code, s.name AS status_name
            FROM tickets t
            LEFT JOIN properties p ON p.id = t.property_id
            LEFT JOIN ticket_status s ON s.id = t.status_id' . $where . '
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT :limit OFFSET :offset',
            $params
        );
    }

    public function personalTicketDetails(int $ticketId, int $userId): ?array
    {
        if ($ticketId < 1 || $userId < 1) {
            return null;
        }

        $rows = $this->db->fetchAllQuery(
            'SELECT t.id, t.property_id, t.status_id, t.title, t.description, t.observation,
                   t.created_by, t.assigned_to, t.created_at, t.updated_at, t.closed_at,
                   p.ident AS property_ident, p.description AS property_description,
                   p.status AS property_status, p.grupo AS property_grupo,
                   p.subgrupo AS property_subgrupo, p.seccion AS property_seccion,
                   s.code AS status_code, s.name AS status_name
            FROM tickets t
            LEFT JOIN properties p ON p.id = t.property_id
            LEFT JOIN ticket_status s ON s.id = t.status_id
            WHERE t.id = :ticket_id AND t.created_by = :created_by AND t.deleted_at IS NULL
            LIMIT 1',
            array(':ticket_id' => $ticketId, ':created_by' => $userId)
        );

        if (!$rows) {
            return null;
        }

        $ticket = $rows[0];
        $ticket['property_category'] = property_category(array(
            'grupo' => (string) ($ticket['property_grupo'] ?? ''),
            'subgrupo' => (string) ($ticket['property_subgrupo'] ?? ''),
            'seccion' => (string) ($ticket['property_seccion'] ?? ''),
        ));
        $ticket['history'] = $this->ticketHistoryWithActors($ticketId);
        return $ticket;
    }

    public function softDeleteTicket(int $ticketId, int $actorUserId, string $deletedAt): bool
    {
        if ($ticketId < 1 || $actorUserId < 1) {
            throw new InvalidArgumentException('Identificadores de ticket o usuario inválidos.');
        }

        if (!$this->db->exec('BEGIN IMMEDIATE')) {
            throw new RuntimeException('No se pudo iniciar la eliminación lógica del ticket: ' . $this->db->lastErrorMsg());
        }

        try {
            $ticket = $this->db->getFirstForConditions(
                'tickets',
                array(array('id', '=', $ticketId), array('deleted_at', 'IS', null))
            );
            if (!$ticket) {
                $this->db->exec('ROLLBACK');
                return false;
            }

            $stmt = $this->db->prepare(
                'UPDATE tickets SET deleted_at = :deleted_at WHERE id = :id AND deleted_at IS NULL'
            );
            if (!$stmt) {
                throw new RuntimeException('Error preparando eliminación lógica: ' . $this->db->lastErrorMsg());
            }
            $stmt->bindValue(':deleted_at', $deletedAt, SQLITE3_TEXT);
            $stmt->bindValue(':id', $ticketId, SQLITE3_INTEGER);
            if (!$stmt->execute()) {
                throw new RuntimeException('Error eliminando ticket: ' . $this->db->lastErrorMsg());
            }

            if ($this->db->changes() !== 1) {
                $this->db->exec('ROLLBACK');
                return false;
            }

            $this->db->create('ticket_histories', array(
                'ticket_id' => $ticketId,
                'actor_user_id' => $actorUserId,
                'action_type' => 'deleted',
                'description' => 'Ticket eliminado; el historial se conserva.',
                'observation' => null,
                'from_status_id' => (int) $ticket['status_id'],
                'to_status_id' => (int) $ticket['status_id'],
                'created_at' => $deletedAt,
            ));

            if (!$this->db->exec('COMMIT')) {
                throw new RuntimeException('No se pudo confirmar la eliminación lógica: ' . $this->db->lastErrorMsg());
            }

            return true;
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }
}
