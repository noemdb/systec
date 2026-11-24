<?php

/**
 * Repositorio de Informes de Rendición (rendition_reports).
 *
 * Centraliza la validación y normalización de alcance/período, el cálculo
 * del hash de unicidad (scope_hash), la generación de códigos correlativos
 * (IR-YYYY-NNNN), la recopilación de mantenimientos y tickets asociados en
 * el rango, el ensamblado con la plantilla Markdown y la persistencia con
 * ciclo de vida borrador -> aprobado.
 */
class RenditionReportRepository
{
    /** @var DB */
    private $db;

    /** @var bool */
    private $tableInitialized = false;

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * Garantiza que la tabla rendition_reports exista en cualquier entorno
     * (incluyendo bases SQLite previas a la migración 008 o tests en memoria).
     */
    public function ensureTableExists(): void
    {
        if ($this->tableInitialized) {
            return;
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS rendition_reports (
            id                    INTEGER PRIMARY KEY AUTOINCREMENT,
            report_code           TEXT NOT NULL UNIQUE,
            title                 TEXT NOT NULL,
            date_from             TEXT NOT NULL,
            date_to               TEXT NOT NULL,
            scope_hash            TEXT NOT NULL UNIQUE,
            filters_json          TEXT NOT NULL DEFAULT '{}',
            parameters_json       TEXT NOT NULL DEFAULT '{}',
            stats_json            TEXT NOT NULL DEFAULT '{}',
            maintenance_ids_json  TEXT NOT NULL DEFAULT '[]',
            ticket_ids_json       TEXT NOT NULL DEFAULT '[]',
            content_markdown      TEXT NOT NULL,
            ai_generated          INTEGER NOT NULL DEFAULT 0,
            status                TEXT NOT NULL DEFAULT 'borrador'
                                  CHECK (status IN ('borrador', 'aprobado')),
            created_by            INTEGER NOT NULL,
            approved_by           INTEGER,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_at           DATETIME
        )");

        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_rendition_reports_dates
            ON rendition_reports (date_from DESC, date_to DESC, id DESC)');

        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_rendition_reports_status
            ON rendition_reports (status, updated_at DESC)');

        $this->tableInitialized = true;
    }

    /**
     * Valida y normaliza una fecha en formato YYYY-MM-DD.
     */
    public function normalizeIsoDate(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return '';
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Normaliza los filtros de alcance del informe en orden canónico.
     */
    public function normalizeFilters(array $rawFilters): array
    {
        $allowedCategories = array('informatica', 'redes', 'otros');
        $allowedStatuses = array_keys($this->db->getListMaintenanceStatus());
        $allowedTypes = array_keys($this->db->getListMaintenanceType());

        $category = isset($rawFilters['category']) && is_string($rawFilters['category'])
            ? mb_strtolower(trim($rawFilters['category']), 'UTF-8')
            : '';
        if (!in_array($category, $allowedCategories, true)) {
            $category = '';
        }

        $status = isset($rawFilters['status']) && is_string($rawFilters['status'])
            ? mb_strtolower(trim($rawFilters['status']), 'UTF-8')
            : '';
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $type = isset($rawFilters['type']) && is_string($rawFilters['type'])
            ? mb_strtolower(trim($rawFilters['type']), 'UTF-8')
            : '';
        if (!in_array($type, $allowedTypes, true)) {
            $type = '';
        }

        $technician = isset($rawFilters['technician']) && is_string($rawFilters['technician'])
            ? mb_substr(trim($rawFilters['technician']), 0, 120, 'UTF-8')
            : '';

        $adscription = isset($rawFilters['adscription']) && is_string($rawFilters['adscription'])
            ? mb_substr(trim($rawFilters['adscription']), 0, 128, 'UTF-8')
            : '';

        $adscriptionLabel = isset($rawFilters['adscription_label']) && is_string($rawFilters['adscription_label'])
            ? mb_substr(trim($rawFilters['adscription_label']), 0, 160, 'UTF-8')
            : '';

        return array(
            'category' => $category,
            'adscription' => $adscription,
            'adscription_label' => $adscription !== '' ? $adscriptionLabel : '',
            'status' => $status,
            'type' => $type,
            'technician' => $technician,
        );
    }

    /**
     * Normaliza los parámetros adicionales configurados en el wizard.
     */
    public function normalizeParameters(array $rawParams, string $defaultPreparedBy = ''): array
    {
        $cleanText = static function ($val, string $fallback, int $maxLength): string {
            if (!is_string($val)) {
                return $fallback;
            }
            $normalized = trim((string) preg_replace('/[ \t]+/u', ' ', $val));
            if ($normalized === '') {
                return $fallback;
            }
            return mb_substr($normalized, 0, $maxLength, 'UTF-8');
        };

        $title = $cleanText(
            $rawParams['title'] ?? null,
            'INFORME DE RENDICIÓN DE MANTENIMIENTO Y ACTIVIDADES ASOCIADAS',
            180
        );
        $issuingUnit = $cleanText(
            $rawParams['issuing_unit'] ?? null,
            'Oficina de Informática · San Felipe, Estado Yaracuy',
            160
        );
        $recipientName = $cleanText(
            $rawParams['recipient_name'] ?? null,
            'Dirección / Coordinación de Bienes Institucionales',
            160
        );
        $preparedBy = $cleanText(
            $rawParams['prepared_by'] ?? null,
            $defaultPreparedBy !== '' ? $defaultPreparedBy : 'Equipo Técnico SysTec',
            120
        );

        $includeTickets = !isset($rawParams['include_tickets'])
            || (string) $rawParams['include_tickets'] === '1'
            || $rawParams['include_tickets'] === true
            || $rawParams['include_tickets'] === 'true';

        $customNotes = '';
        if (isset($rawParams['custom_notes']) && is_string($rawParams['custom_notes'])) {
            $customNotes = mb_substr(trim($rawParams['custom_notes']), 0, 2000, 'UTF-8');
        }

        return array(
            'title' => $title,
            'issuing_unit' => $issuingUnit,
            'recipient_name' => $recipientName,
            'prepared_by' => $preparedBy,
            'include_tickets' => $includeTickets,
            'custom_notes' => $customNotes,
        );
    }

    /**
     * Calcula el hash SHA-256 único para la combinación (date_from, date_to, filtros normalizados).
     */
    public function computeScopeHash(string $dateFrom, string $dateTo, array $filters = array()): string
    {
        $normalizedDateFrom = $this->normalizeIsoDate($dateFrom);
        $normalizedDateTo = $this->normalizeIsoDate($dateTo);
        if ($normalizedDateFrom === '' || $normalizedDateTo === '') {
            throw new InvalidArgumentException('El rango de fechas para el Informe de Rendición es inválido.');
        }
        if ($normalizedDateFrom > $normalizedDateTo) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $canonicalPayload = array(
            'date_from' => $normalizedDateFrom,
            'date_to' => $normalizedDateTo,
            'category' => $normalizedFilters['category'],
            'adscription' => $normalizedFilters['adscription'],
            'status' => $normalizedFilters['status'],
            'type' => $normalizedFilters['type'],
            'technician' => mb_strtolower($normalizedFilters['technician'], 'UTF-8'),
        );

        return hash('sha256', (string) json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Construye una descripción legible de los criterios de alcance aplicados.
     */
    public function buildScopeSummary(array $filters): string
    {
        $normalized = $this->normalizeFilters($filters);
        $parts = array();

        $categoryLabels = array(
            'informatica' => 'Informática',
            'redes' => 'Redes y telecomunicaciones',
            'otros' => 'Otros bienes',
        );
        if ($normalized['category'] !== '') {
            $parts[] = 'Categoría: ' . ($categoryLabels[$normalized['category']] ?? $normalized['category']);
        }
        if ($normalized['adscription'] !== '') {
            $label = $normalized['adscription_label'] !== ''
                ? $normalized['adscription_label']
                : $normalized['adscription'];
            $parts[] = 'Unidad: ' . $label;
        }
        if ($normalized['status'] !== '') {
            $parts[] = 'Estado: ' . texto_mantenimiento($normalized['status']);
        }
        if ($normalized['type'] !== '') {
            $parts[] = 'Tipo: ' . texto_tipo($normalized['type']);
        }
        if ($normalized['technician'] !== '') {
            $parts[] = 'Técnico: ' . $normalized['technician'];
        }

        return $parts !== array()
            ? implode(' · ', $parts)
            : 'Todos los mantenimientos registrados en el período';
    }

    /**
     * Busca si ya existe un informe para el scope_hash dado.
     */
    public function findByScopeHash(string $scopeHash): ?array
    {
        $this->ensureTableExists();
        $scopeHash = trim($scopeHash);
        if ($scopeHash === '') {
            return null;
        }

        $rows = $this->db->fetchAllQuery(
            'SELECT r.*, u_created.username AS created_by_username, u_approved.username AS approved_by_username
             FROM rendition_reports r
             LEFT JOIN users u_created ON u_created.id = r.created_by
             LEFT JOIN users u_approved ON u_approved.id = r.approved_by
             WHERE r.scope_hash = :scope_hash
             LIMIT 1',
            array(':scope_hash' => $scopeHash)
        );

        return $rows !== array() ? $this->hydrateReportRow($rows[0]) : null;
    }

    /**
     * Obtiene un informe por su ID.
     */
    public function findById(int $id): ?array
    {
        $this->ensureTableExists();
        if ($id < 1) {
            return null;
        }

        $rows = $this->db->fetchAllQuery(
            'SELECT r.*, u_created.username AS created_by_username, u_approved.username AS approved_by_username
             FROM rendition_reports r
             LEFT JOIN users u_created ON u_created.id = r.created_by
             LEFT JOIN users u_approved ON u_approved.id = r.approved_by
             WHERE r.id = :id
             LIMIT 1',
            array(':id' => $id)
        );

        return $rows !== array() ? $this->hydrateReportRow($rows[0]) : null;
    }

    /**
     * Lista los informes de rendición guardados (más recientes primero).
     */
    public function listReports(int $limit = 50): array
    {
        $this->ensureTableExists();
        $limit = max(1, min(200, $limit));

        $rows = $this->db->fetchAllQuery(
            'SELECT r.*, u_created.username AS created_by_username, u_approved.username AS approved_by_username
             FROM rendition_reports r
             LEFT JOIN users u_created ON u_created.id = r.created_by
             LEFT JOIN users u_approved ON u_approved.id = r.approved_by
             ORDER BY r.id DESC
             LIMIT ' . $limit
        );

        $result = array();
        foreach ($rows as $row) {
            $result[] = $this->hydrateReportRow($row);
        }

        return $result;
    }

    /**
     * Cuenta el total de informes de rendición guardados.
     */
    public function countReports(): int
    {
        $this->ensureTableExists();
        return $this->db->countRows('rendition_reports');
    }

    /**
     * Elimina un Informe de Rendición por su ID.
     */
    public function deleteReport(int $id): bool
    {
        $this->ensureTableExists();
        if ($id < 1) {
            throw new InvalidArgumentException('Identificador de informe inválido.');
        }

        $stmt = $this->db->prepare('DELETE FROM rendition_reports WHERE id = :id');
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la eliminación del Informe de Rendición.');
        }
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) {
            throw new RuntimeException('Error al eliminar el Informe de Rendición.');
        }

        return $this->db->changes() > 0;
    }

    /**
     * Genera el siguiente código correlativo único con formato IR-YYYY-NNNN.
     */
    public function generateNextReportCode(?string $year = null): string
    {
        $this->ensureTableExists();
        $targetYear = ($year !== null && preg_match('/^\d{4}$/', $year)) ? $year : date('Y');
        $prefix = 'IR-' . $targetYear . '-';

        $rows = $this->db->fetchAllQuery(
            'SELECT report_code FROM rendition_reports WHERE report_code LIKE :prefix ORDER BY report_code DESC LIMIT 1',
            array(':prefix' => $prefix . '%')
        );

        $nextNumber = 1;
        if ($rows !== array()) {
            $lastCode = (string) ($rows[0]['report_code'] ?? '');
            if (preg_match('/^IR-\d{4}-(\d+)$/', $lastCode, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            }
        }

        return sprintf('%s%04d', $prefix, $nextNumber);
    }

    /**
     * Recopila todos los mantenimientos, bienes y tickets asociados dentro del período y alcance.
     */
    public function collectPeriodData(
        string $dateFrom,
        string $dateTo,
        array $filters = array(),
        bool $includeTickets = true,
        array $legacyAdscriptionsByIdent = array()
    ): array {
        $normalizedDateFrom = $this->normalizeIsoDate($dateFrom);
        $normalizedDateTo = $this->normalizeIsoDate($dateTo);
        if ($normalizedDateFrom === '' || $normalizedDateTo === '') {
            throw new InvalidArgumentException('Debes indicar un rango de fechas válido (Fecha desde y Fecha hasta).');
        }
        if ($normalizedDateFrom > $normalizedDateTo) {
            throw new InvalidArgumentException('La fecha desde no puede ser mayor que la fecha hasta.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);

        $rows = $this->db->fetchAllQuery(
            "SELECT m.*,
                    p.grupo AS property_grupo,
                    p.subgrupo AS property_subgrupo,
                    p.seccion AS property_seccion,
                    p.ident AS property_ident,
                    p.adscription AS property_adscription,
                    p.description AS property_description,
                    p.model AS property_model,
                    p.serial AS property_serial,
                    p.status AS property_status
             FROM maintenances m
             LEFT JOIN properties p ON p.id = m.property_id
             WHERE substr(TRIM(COALESCE(m.date, '')), 1, 10) >= :date_from
               AND substr(TRIM(COALESCE(m.date, '')), 1, 10) <= :date_to
             ORDER BY datetime(TRIM(m.date)) ASC, m.id ASC",
            array(
                ':date_from' => $normalizedDateFrom,
                ':date_to' => $normalizedDateTo,
            )
        );

        $matchedMaintenances = array();
        $propertyIds = array();
        $linkedTicketIds = array();
        $totalHours = 0;
        $byStatus = array('iniciado' => 0, 'revision' => 0, 'finalizado' => 0);
        $byType = array('preventivo' => 0, 'correctivo' => 0, 'predictivo' => 0, 'evaluativo' => 0);
        $byCategory = array('informatica' => 0, 'redes' => 0, 'otros' => 0);
        $countedPropertyCategories = array();
        $technicians = array();
        $institutionalAdscriptionsByLabel = property_adscription_institutional_index($legacyAdscriptionsByIdent);

        foreach ($rows as $row) {
            $propertyStub = array(
                'id' => (int) ($row['property_id'] ?? 0),
                'grupo' => (string) ($row['property_grupo'] ?? ''),
                'subgrupo' => (string) ($row['property_subgrupo'] ?? ''),
                'seccion' => (string) ($row['property_seccion'] ?? ''),
                'ident' => (string) ($row['property_ident'] ?? ''),
                'adscription' => (string) ($row['property_adscription'] ?? ''),
            );
            $category = property_category($propertyStub);
            $adscriptionAssignment = property_adscription_assignment(
                $propertyStub,
                $legacyAdscriptionsByIdent,
                $institutionalAdscriptionsByLabel
            );
            $statusKey = mb_strtolower(trim((string) ($row['status'] ?? '')), 'UTF-8');
            $typeKey = mb_strtolower(trim((string) ($row['type'] ?? '')), 'UTF-8');
            $technicianName = trim((string) ($row['technician'] ?? ''));

            if ($normalizedFilters['category'] !== '' && $category !== $normalizedFilters['category']) {
                continue;
            }
            if ($normalizedFilters['adscription'] !== '' && $adscriptionAssignment['key'] !== $normalizedFilters['adscription']) {
                continue;
            }
            if ($normalizedFilters['status'] !== '' && $statusKey !== $normalizedFilters['status']) {
                continue;
            }
            if ($normalizedFilters['type'] !== '' && $typeKey !== $normalizedFilters['type']) {
                continue;
            }
            if ($normalizedFilters['technician'] !== ''
                && mb_strtolower($technicianName, 'UTF-8') !== mb_strtolower($normalizedFilters['technician'], 'UTF-8')) {
                continue;
            }

            if ($normalizedFilters['adscription'] !== '' && $normalizedFilters['adscription_label'] === '') {
                $normalizedFilters['adscription_label'] = $adscriptionAssignment['label'];
            }

            $propertyCode = $propertyStub['ident'] !== ''
                ? property_code($propertyStub)
                : trim((string) ($row['code'] ?? '—'));
            $row['resolved_code'] = $propertyCode !== '' ? $propertyCode : '—';
            $row['resolved_category'] = $category;
            $row['resolved_adscription'] = $adscriptionAssignment['label'];
            $matchedMaintenances[] = $row;

            $pid = (int) ($row['property_id'] ?? 0);
            if ($pid > 0) {
                $propertyIds[$pid] = $pid;
                if (!isset($countedPropertyCategories[$pid])) {
                    $countedPropertyCategories[$pid] = true;
                    if (isset($byCategory[$category])) {
                        $byCategory[$category]++;
                    }
                }
            }

            if (isset($row['ticket_id']) && (int) $row['ticket_id'] > 0) {
                $tid = (int) $row['ticket_id'];
                $linkedTicketIds[$tid] = $tid;
            }

            $hours = is_numeric($row['time_taken'] ?? null) ? max(0, (int) $row['time_taken']) : 0;
            $totalHours += $hours;

            if (isset($byStatus[$statusKey])) {
                $byStatus[$statusKey]++;
            }
            if (isset($byType[$typeKey])) {
                $byType[$typeKey]++;
            }
            if ($technicianName !== '') {
                $technicians[$technicianName] = ($technicians[$technicianName] ?? 0) + 1;
            }
        }

        $tickets = array();
        $ticketsClosed = 0;
        $ticketsOpen = 0;
        if ($includeTickets && ($propertyIds !== array() || $linkedTicketIds !== array())) {
            $tickets = $this->fetchAssociatedTickets(
                array_values($propertyIds),
                array_values($linkedTicketIds),
                $normalizedDateFrom,
                $normalizedDateTo
            );
            foreach ($tickets as $ticket) {
                if ((int) ($ticket['is_terminal'] ?? 0) === 1 || ($ticket['status_code'] ?? '') === 'finalizado') {
                    $ticketsClosed++;
                } else {
                    $ticketsOpen++;
                }
            }
        }

        $totalMaintenances = count($matchedMaintenances);
        $avgHours = $totalMaintenances > 0 ? round($totalHours / $totalMaintenances, 1) : 0;

        $stats = array(
            'total_maintenances' => $totalMaintenances,
            'total_properties' => count($propertyIds),
            'total_hours' => $totalHours,
            'avg_hours' => $avgHours,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'by_category' => $byCategory,
            'total_tickets' => count($tickets),
            'tickets_closed' => $ticketsClosed,
            'tickets_open' => $ticketsOpen,
            'technicians' => $technicians,
        );

        return array(
            'date_from' => $normalizedDateFrom,
            'date_to' => $normalizedDateTo,
            'filters' => $normalizedFilters,
            'scope_hash' => $this->computeScopeHash($normalizedDateFrom, $normalizedDateTo, $normalizedFilters),
            'scope_summary' => $this->buildScopeSummary($normalizedFilters),
            'maintenances' => $matchedMaintenances,
            'tickets' => $tickets,
            'stats' => $stats,
            'maintenance_ids' => array_map(static function (array $m): int {
                return (int) $m['id'];
            }, $matchedMaintenances),
            'ticket_ids' => array_map(static function (array $t): int {
                return (int) $t['id'];
            }, $tickets),
        );
    }

    /**
     * Consulta los tickets asociados directamente por ticket_id o pertenecientes a los bienes
     * intervenidos con actividad dentro del período rendido.
     */
    private function fetchAssociatedTickets(
        array $propertyIds,
        array $directTicketIds,
        string $dateFrom,
        string $dateTo
    ): array {
        $clauses = array();
        $params = array();

        if ($directTicketIds !== array()) {
            $tPlaceholders = array();
            foreach (array_values($directTicketIds) as $idx => $tid) {
                $key = ':tid' . $idx;
                $tPlaceholders[] = $key;
                $params[$key] = (int) $tid;
            }
            $clauses[] = 't.id IN (' . implode(', ', $tPlaceholders) . ')';
        }

        if ($propertyIds !== array()) {
            $pPlaceholders = array();
            foreach (array_values($propertyIds) as $idx => $pid) {
                $key = ':pid' . $idx;
                $pPlaceholders[] = $key;
                $params[$key] = (int) $pid;
            }
            $params[':t_date_from'] = $dateFrom;
            $params[':t_date_to'] = $dateTo;
            $clauses[] = '(t.property_id IN (' . implode(', ', $pPlaceholders) . ')
                AND (
                    (substr(TRIM(COALESCE(t.created_at, \'\')), 1, 10) >= :t_date_from AND substr(TRIM(COALESCE(t.created_at, \'\')), 1, 10) <= :t_date_to)
                    OR (substr(TRIM(COALESCE(t.updated_at, \'\')), 1, 10) >= :t_date_from AND substr(TRIM(COALESCE(t.updated_at, \'\')), 1, 10) <= :t_date_to)
                ))';
        }

        if ($clauses === array()) {
            return array();
        }

        return $this->db->fetchAllQuery(
            'SELECT t.id, t.property_id, t.title, t.description, t.observation,
                    t.created_at, t.updated_at, t.closed_at,
                    s.code AS status_code, s.name AS status_name, s.is_terminal,
                    p.grupo AS property_grupo, p.subgrupo AS property_subgrupo,
                    p.seccion AS property_seccion, p.ident AS property_ident,
                    p.description AS property_description,
                    u.username AS creator_username
             FROM tickets t
             LEFT JOIN ticket_status s ON s.id = t.status_id
             LEFT JOIN properties p ON p.id = t.property_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.deleted_at IS NULL
               AND (' . implode(' OR ', $clauses) . ')
             ORDER BY t.created_at ASC, t.id ASC',
            $params
        );
    }

    /**
     * Carga la plantilla Markdown desde /template/informe_rendicion.md.
     */
    public function loadTemplateMarkdown(): string
    {
        $templatePath = __DIR__ . '/../../template/informe_rendicion.md';
        if (!is_file($templatePath) || !is_readable($templatePath)) {
            throw new RuntimeException('No se encontró la plantilla del Informe de Rendición en template/informe_rendicion.md.');
        }

        $content = file_get_contents($templatePath);
        if ($content === false || trim($content) === '') {
            throw new RuntimeException('La plantilla template/informe_rendicion.md está vacía o no se pudo leer.');
        }

        return $content;
    }

    /**
     * Ensambla el documento Markdown completo reemplazando los marcadores de la plantilla
     * con los indicadores, las tablas tipo bitácora y las secciones narrativas de IA.
     */
    public function renderReportMarkdown(
        array $periodData,
        array $parameters,
        array $aiSections,
        string $reportCode,
        string $status = 'borrador',
        ?string $approvedAt = null
    ): string {
        $template = $this->loadTemplateMarkdown();
        $stats = $periodData['stats'];

        $dateFromTs = strtotime($periodData['date_from']);
        $dateToTs = strtotime($periodData['date_to']);
        $dateFromLabel = $dateFromTs !== false ? date('d/m/Y', $dateFromTs) : $periodData['date_from'];
        $dateToLabel = $dateToTs !== false ? date('d/m/Y', $dateToTs) : $periodData['date_to'];

        $statusLabel = $status === 'aprobado' ? 'APROBADO' : 'BORRADOR EN REVISIÓN';
        $approvedAtLabel = $approvedAt !== null && trim($approvedAt) !== ''
            ? date('d/m/Y H:i', strtotime($approvedAt) ?: time())
            : ($status === 'aprobado' ? date('d/m/Y H:i') : 'Pendiente de aprobación');

        $byStatus = $stats['by_status'];
        $breakdownByStatus = sprintf(
            'Finalizados: %d · En revisión: %d · Iniciados: %d',
            (int) ($byStatus['finalizado'] ?? 0),
            (int) ($byStatus['revision'] ?? 0),
            (int) ($byStatus['iniciado'] ?? 0)
        );

        $byCategory = $stats['by_category'];
        $breakdownByCategory = sprintf(
            'Informática: %d · Redes: %d · Otros: %d',
            (int) ($byCategory['informatica'] ?? 0),
            (int) ($byCategory['redes'] ?? 0),
            (int) ($byCategory['otros'] ?? 0)
        );

        $maintenanceTable = $this->buildMaintenanceMarkdownTable($periodData['maintenances']);
        $ticketsTable = $this->buildTicketsMarkdownTable($periodData['tickets'], !empty($parameters['include_tickets']));

        $replacements = array(
            '{{REPORT_TITLE}}' => (string) ($parameters['title'] ?? 'INFORME DE RENDICIÓN DE MANTENIMIENTO Y ACTIVIDADES ASOCIADAS'),
            '{{REPORT_CODE}}' => $reportCode,
            '{{REPORT_STATUS_LABEL}}' => $statusLabel,
            '{{DATE_FROM_LABEL}}' => $dateFromLabel,
            '{{DATE_TO_LABEL}}' => $dateToLabel,
            '{{ISSUING_UNIT}}' => (string) ($parameters['issuing_unit'] ?? ''),
            '{{RECIPIENT_NAME}}' => (string) ($parameters['recipient_name'] ?? ''),
            '{{PREPARED_BY}}' => (string) ($parameters['prepared_by'] ?? ''),
            '{{ISSUED_AT}}' => date('d/m/Y H:i'),
            '{{SCOPE_SUMMARY}}' => (string) ($periodData['scope_summary'] ?? ''),
            '{{AI_EXECUTIVE_SUMMARY}}' => trim((string) ($aiSections['executive_summary'] ?? '')),
            '{{TOTAL_MAINTENANCES}}' => (string) ((int) ($stats['total_maintenances'] ?? 0)),
            '{{BREAKDOWN_BY_STATUS}}' => $breakdownByStatus,
            '{{TOTAL_PROPERTIES}}' => (string) ((int) ($stats['total_properties'] ?? 0)),
            '{{BREAKDOWN_BY_CATEGORY}}' => $breakdownByCategory,
            '{{TOTAL_HOURS}}' => (string) ((int) ($stats['total_hours'] ?? 0)),
            '{{AVG_HOURS}}' => (string) ($stats['avg_hours'] ?? 0),
            '{{COUNT_PREVENTIVO}}' => (string) ((int) ($stats['by_type']['preventivo'] ?? 0)),
            '{{COUNT_CORRECTIVO}}' => (string) ((int) ($stats['by_type']['correctivo'] ?? 0)),
            '{{COUNT_PREDICTIVO}}' => (string) ((int) ($stats['by_type']['predictivo'] ?? 0)),
            '{{COUNT_EVALUATIVO}}' => (string) ((int) ($stats['by_type']['evaluativo'] ?? 0)),
            '{{TOTAL_TICKETS}}' => (string) ((int) ($stats['total_tickets'] ?? 0)),
            '{{TICKETS_CLOSED}}' => (string) ((int) ($stats['tickets_closed'] ?? 0)),
            '{{TICKETS_OPEN}}' => (string) ((int) ($stats['tickets_open'] ?? 0)),
            '{{AI_ACTIVITIES_NARRATIVE}}' => trim((string) ($aiSections['activities_narrative'] ?? '')),
            '{{MAINTENANCE_LOG_TABLE}}' => $maintenanceTable,
            '{{AI_TICKETS_NARRATIVE}}' => trim((string) ($aiSections['tickets_narrative'] ?? '')),
            '{{TICKETS_LOG_TABLE}}' => $ticketsTable,
            '{{AI_OBSERVATIONS_AND_CONCLUSIONS}}' => trim((string) ($aiSections['observations_and_conclusions'] ?? '')),
            '{{APPROVED_AT_LABEL}}' => $approvedAtLabel,
        );

        return strtr($template, $replacements);
    }

    /**
     * Construye la tabla cronológica de mantenimientos en formato HTML con rowspan/colspan
     * para ubicar "Trabajo Realizado / Causa / Notas" justo debajo de las 8 columnas de metadatos
     * ("Fecha | Código del Bien | Descripción del Bien | Tipo | Estado | Técnico | Horas | Ticket").
     */
    private function buildMaintenanceMarkdownTable(array $maintenances): string
    {
        if ($maintenances === array()) {
            return '_No se encontraron acciones de mantenimiento para el período y criterios seleccionados._';
        }

        $escapeHtml = static function (string $value): string {
            $clean = trim((string) preg_replace('/\s+/u', ' ', $value));
            if ($clean === '') {
                return '—';
            }
            return htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $lines = array(
            '<table class="table table-sm table-bordered align-middle mb-0 rendition-markdown-table rendition-maintenance-log-table">',
            '  <thead class="table-light">',
            '    <tr>',
            '      <th scope="col" rowspan="2" class="text-center">#</th>',
            '      <th scope="col">Fecha</th>',
            '      <th scope="col">Código del Bien</th>',
            '      <th scope="col">Descripción del Bien</th>',
            '      <th scope="col">Tipo</th>',
            '      <th scope="col">Estado</th>',
            '      <th scope="col">Técnico</th>',
            '      <th scope="col" class="text-center">Horas</th>',
            '      <th scope="col" class="text-center">Ticket</th>',
            '    </tr>',
            '    <tr>',
            '      <th scope="col" colspan="8" class="small text-body-secondary fw-semibold">Trabajo Realizado / Causa / Notas</th>',
            '    </tr>',
            '  </thead>',
            '  <tbody>',
        );

        foreach ($maintenances as $index => $m) {
            $dateRaw = trim((string) ($m['date'] ?? ''));
            $dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
            $dateLabel = $dateTs !== false ? date('d/m/Y H:i', $dateTs) : ($dateRaw !== '' ? $dateRaw : '—');

            $code = (string) ($m['resolved_code'] ?? $m['code'] ?? '—');
            $propDesc = trim((string) ($m['property_description'] ?? ''));
            if (mb_strlen($propDesc, 'UTF-8') > 70) {
                $propDesc = mb_substr($propDesc, 0, 67, 'UTF-8') . '...';
            }

            $typeLabel = texto_tipo((string) ($m['type'] ?? ''));
            $statusLabel = texto_mantenimiento((string) ($m['status'] ?? ''));
            $tech = (string) ($m['technician'] ?? '—');
            $hours = trim((string) ($m['time_taken'] ?? '')) !== '' ? (string) $m['time_taken'] . ' h' : '—';
            $ticketLabel = isset($m['ticket_id']) && (int) $m['ticket_id'] > 0
                ? '#' . (int) $m['ticket_id']
                : '—';

            $workParts = array();
            $desc = trim((string) ($m['description'] ?? ''));
            $reason = trim((string) ($m['failure_reason'] ?? ''));
            $notes = trim((string) ($m['notes'] ?? ''));
            if ($desc !== '') {
                $workParts[] = '<strong>Trabajo Realizado:</strong> ' . $escapeHtml($desc);
            }
            if ($reason !== '') {
                $workParts[] = '<strong>Causa:</strong> ' . $escapeHtml($reason);
            }
            if ($notes !== '') {
                $workParts[] = '<strong>Notas:</strong> ' . $escapeHtml($notes);
            }
            $workSummaryHtml = $workParts !== array()
                ? implode(' · ', $workParts)
                : '<strong>Trabajo Realizado / Causa / Notas:</strong> —';

            $lines[] = '    <tr class="rendition-maintenance-meta-row">';
            $lines[] = sprintf('      <td rowspan="2" class="text-center fw-semibold">%d</td>', $index + 1);
            $lines[] = sprintf('      <td class="text-nowrap">%s</td>', $escapeHtml($dateLabel));
            $lines[] = sprintf('      <td class="text-nowrap fw-semibold">%s</td>', $escapeHtml($code));
            $lines[] = sprintf('      <td>%s</td>', $escapeHtml($propDesc));
            $lines[] = sprintf('      <td>%s</td>', $escapeHtml($typeLabel));
            $lines[] = sprintf('      <td>%s</td>', $escapeHtml($statusLabel));
            $lines[] = sprintf('      <td>%s</td>', $escapeHtml($tech));
            $lines[] = sprintf('      <td class="text-center text-nowrap">%s</td>', $escapeHtml($hours));
            $lines[] = sprintf('      <td class="text-center text-nowrap">%s</td>', $escapeHtml($ticketLabel));
            $lines[] = '    </tr>';
            $lines[] = '    <tr class="rendition-maintenance-detail-row">';
            $lines[] = sprintf('      <td colspan="8" class="bg-body-tertiary small">%s</td>', $workSummaryHtml);
            $lines[] = '    </tr>';
        }

        $lines[] = '  </tbody>';
        $lines[] = '</table>';

        return implode("\n", $lines);
    }

    /**
     * Construye la tabla Markdown de tickets asociados al período.
     */
    private function buildTicketsMarkdownTable(array $tickets, bool $includeTickets): string
    {
        if (!$includeTickets) {
            return '_La inclusión de tickets asociados fue desactivada en los parámetros de este informe._';
        }
        if ($tickets === array()) {
            return '_No se registraron tickets asociados a los bienes o mantenimientos en este período._';
        }

        $escapeCell = static function (string $value): string {
            $clean = trim((string) preg_replace('/\s+/u', ' ', $value));
            if ($clean === '') {
                return '—';
            }
            return str_replace('|', '\\|', $clean);
        };

        $lines = array(
            '| Ticket | Fecha Creación | Bien Asociado | Asunto / Título | Estado | Descripción y Observaciones |',
            '| :---: | :--- | :--- | :--- | :--- | :--- |',
        );

        foreach ($tickets as $ticket) {
            $createdRaw = trim((string) ($ticket['created_at'] ?? ''));
            $createdTs = $createdRaw !== '' ? strtotime($createdRaw) : false;
            $createdLabel = $createdTs !== false ? date('d/m/Y H:i', $createdTs) : ($createdRaw !== '' ? $createdRaw : '—');

            $propStub = array(
                'grupo' => (string) ($ticket['property_grupo'] ?? ''),
                'subgrupo' => (string) ($ticket['property_subgrupo'] ?? ''),
                'seccion' => (string) ($ticket['property_seccion'] ?? ''),
                'ident' => (string) ($ticket['property_ident'] ?? ''),
            );
            $propCode = $propStub['ident'] !== '' ? property_code($propStub) : 'ID ' . (int) ($ticket['property_id'] ?? 0);

            $detailParts = array();
            $tDesc = trim((string) ($ticket['description'] ?? ''));
            $tObs = trim((string) ($ticket['observation'] ?? ''));
            if ($tDesc !== '') {
                $detailParts[] = $tDesc;
            }
            if ($tObs !== '') {
                $detailParts[] = 'Obs.: ' . $tObs;
            }

            $lines[] = sprintf(
                '| #%d | %s | %s | %s | %s | %s |',
                (int) $ticket['id'],
                $escapeCell($createdLabel),
                $escapeCell($propCode),
                $escapeCell((string) ($ticket['title'] ?? '')),
                $escapeCell((string) ($ticket['status_name'] ?? $ticket['status_code'] ?? '')),
                $escapeCell(implode(' · ', $detailParts))
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Guarda o actualiza un Informe de Rendición respetando la unicidad por scope_hash
     * y la inmutabilidad de los informes aprobados.
     */
    public function saveReport(array $payload, int $actorUserId): array
    {
        $this->ensureTableExists();
        if ($actorUserId < 1) {
            throw new InvalidArgumentException('Usuario autenticador inválido.');
        }

        $dateFrom = $this->normalizeIsoDate((string) ($payload['date_from'] ?? ''));
        $dateTo = $this->normalizeIsoDate((string) ($payload['date_to'] ?? ''));
        if ($dateFrom === '' || $dateTo === '' || $dateFrom > $dateTo) {
            throw new InvalidArgumentException('El rango de fechas del informe es inválido.');
        }

        $filters = $this->normalizeFilters(
            isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : array()
        );
        $parameters = $this->normalizeParameters(
            isset($payload['parameters']) && is_array($payload['parameters']) ? $payload['parameters'] : array()
        );
        $scopeHash = $this->computeScopeHash($dateFrom, $dateTo, $filters);

        $contentMarkdown = isset($payload['content_markdown']) && is_string($payload['content_markdown'])
            ? trim($payload['content_markdown'])
            : '';
        if ($contentMarkdown === '') {
            throw new InvalidArgumentException('El contenido del Informe de Rendición no puede estar vacío.');
        }
        if (mb_strlen($contentMarkdown, 'UTF-8') > 150000) {
            throw new InvalidArgumentException('El contenido del informe excede la longitud máxima permitida.');
        }

        $targetStatus = isset($payload['status']) && $payload['status'] === 'aprobado'
            ? 'aprobado'
            : 'borrador';
        $aiGenerated = !empty($payload['ai_generated']) ? 1 : 0;

        $stats = isset($payload['stats']) && is_array($payload['stats']) ? $payload['stats'] : array();
        $maintenanceIds = isset($payload['maintenance_ids']) && is_array($payload['maintenance_ids'])
            ? array_values(array_map('intval', $payload['maintenance_ids']))
            : array();
        $ticketIds = isset($payload['ticket_ids']) && is_array($payload['ticket_ids'])
            ? array_values(array_map('intval', $payload['ticket_ids']))
            : array();

        $now = date('Y-m-d H:i:s');
        $existingByScope = $this->findByScopeHash($scopeHash);
        $requestedId = isset($payload['id']) && $payload['id'] !== ''
            ? filter_var($payload['id'], FILTER_VALIDATE_INT)
            : null;

        if ($existingByScope !== null) {
            if ((string) $existingByScope['status'] === 'aprobado') {
                throw new RuntimeException(
                    'Ya existe un Informe de Rendición aprobado (' . $existingByScope['report_code']
                    . ') para este período y alcance. Los informes aprobados son definitivos.'
                );
            }
            $targetId = (int) $existingByScope['id'];
        } elseif ($requestedId !== null && $requestedId !== false && $requestedId > 0) {
            $existingById = $this->findById((int) $requestedId);
            if ($existingById === null) {
                throw new RuntimeException('No se encontró el borrador de Informe de Rendición indicado.');
            }
            if ((string) $existingById['status'] === 'aprobado') {
                throw new RuntimeException(
                    'El Informe de Rendición ' . $existingById['report_code']
                    . ' ya fue aprobado y no admite modificaciones.'
                );
            }
            $targetId = (int) $existingById['id'];
        } else {
            $targetId = null;
        }

        if ($targetStatus === 'aprobado') {
            $approvedLabel = date('d/m/Y H:i', strtotime($now));
            $contentMarkdown = str_replace(
                array('BORRADOR EN REVISIÓN', 'Pendiente de aprobación'),
                array('APROBADO', $approvedLabel),
                $contentMarkdown
            );
        }

        if ($targetId !== null) {
            $updateData = array(
                'title' => $parameters['title'],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'scope_hash' => $scopeHash,
                'filters_json' => (string) json_encode($filters, JSON_UNESCAPED_UNICODE),
                'parameters_json' => (string) json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'stats_json' => (string) json_encode($stats, JSON_UNESCAPED_UNICODE),
                'maintenance_ids_json' => (string) json_encode($maintenanceIds),
                'ticket_ids_json' => (string) json_encode($ticketIds),
                'content_markdown' => $contentMarkdown,
                'ai_generated' => $aiGenerated,
                'status' => $targetStatus,
                'updated_at' => $now,
            );
            if ($targetStatus === 'aprobado') {
                $updateData['approved_by'] = $actorUserId;
                $updateData['approved_at'] = $now;
            }

            $this->db->update('rendition_reports', $targetId, $updateData);
            $saved = $this->findById($targetId);
            if ($saved === null) {
                throw new RuntimeException('No se pudo recuperar el Informe de Rendición actualizado.');
            }
            return $saved;
        }

        $reportYear = substr($dateTo, 0, 4);
        $reportCode = $this->generateNextReportCode($reportYear);

        $contentMarkdown = str_replace('{{REPORT_CODE}}', $reportCode, $contentMarkdown);
        $contentMarkdown = preg_replace(
            '/(?:^(\*\*Código de Informe:\*\*\s*)|(\|\s*\*\*Código de Informe\*\*\s*\|\s*`?))IR-\d{4}-BORRADOR(`?)/mu',
            '${1}${2}' . $reportCode . '${3}',
            $contentMarkdown
        ) ?? $contentMarkdown;

        $insertData = array(
            'report_code' => $reportCode,
            'title' => $parameters['title'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'scope_hash' => $scopeHash,
            'filters_json' => (string) json_encode($filters, JSON_UNESCAPED_UNICODE),
            'parameters_json' => (string) json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'stats_json' => (string) json_encode($stats, JSON_UNESCAPED_UNICODE),
            'maintenance_ids_json' => (string) json_encode($maintenanceIds),
            'ticket_ids_json' => (string) json_encode($ticketIds),
            'content_markdown' => $contentMarkdown,
            'ai_generated' => $aiGenerated,
            'status' => $targetStatus,
            'created_by' => $actorUserId,
            'approved_by' => $targetStatus === 'aprobado' ? $actorUserId : null,
            'created_at' => $now,
            'updated_at' => $now,
            'approved_at' => $targetStatus === 'aprobado' ? $now : null,
        );

        $newId = (int) $this->db->create('rendition_reports', $insertData);
        $saved = $this->findById($newId);
        if ($saved === null) {
            throw new RuntimeException('No se pudo recuperar el Informe de Rendición recién guardado.');
        }

        return $saved;
    }

    /**
     * Decodifica los campos JSON de una fila de rendition_reports.
     */
    private function hydrateReportRow(array $row): array
    {
        $decodeJson = static function ($raw, array $default = array()): array {
            if (!is_string($raw) || trim($raw) === '') {
                return $default;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : $default;
        };

        $row['id'] = (int) ($row['id'] ?? 0);
        $row['ai_generated'] = (int) ($row['ai_generated'] ?? 0);
        $row['created_by'] = (int) ($row['created_by'] ?? 0);
        $row['approved_by'] = isset($row['approved_by']) && $row['approved_by'] !== null
            ? (int) $row['approved_by']
            : null;
        $row['filters'] = $decodeJson($row['filters_json'] ?? '{}');
        $row['parameters'] = $decodeJson($row['parameters_json'] ?? '{}');
        $row['stats'] = $decodeJson($row['stats_json'] ?? '{}');
        $row['maintenance_ids'] = $decodeJson($row['maintenance_ids_json'] ?? '[]');
        $row['ticket_ids'] = $decodeJson($row['ticket_ids_json'] ?? '[]');
        $row['scope_summary'] = $this->buildScopeSummary($row['filters']);

        return $row;
    }
}
