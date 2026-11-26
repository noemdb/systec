<?php

date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/security.php';
require_once __DIR__ . '/include/helpers.php';
require_once __DIR__ . '/include/repositories/PropertyRepository.php';
require_once __DIR__ . '/include/repositories/MaintenanceRepository.php';
require_once __DIR__ . '/include/repositories/TicketRepository.php';
require_once __DIR__ . '/include/repositories/RenditionReportRepository.php';

/**
 * Capa de datos de SysTec GMB.
 *
 * Reglas:
 * - Los nombres de tabla/columna se validan contra listas blancas.
 * - Los valores siempre viajan como parámetros vinculados.
 * - La conexión no se cierra dentro de los métodos; la cierra el llamador
 *   o el final del request.
 * - La lógica de dominio por entidad se delega en PropertyRepository,
 *   MaintenanceRepository y TicketRepository (ARCH-01 / ADR-014).
 */
class DB extends SQLite3
{
    private static $schema = array(
        'properties' => array(
            'id', 'grupo', 'subgrupo', 'seccion', 'ident', 'adscription',
            'description', 'model', 'color', 'serial', 'status',
            'responsible_name', 'responsible_status', 'assigned_name', 'assigned_status',
        ),
        'maintenances' => array(
            'id', 'property_id', 'ticket_id', 'code', 'type', 'description', 'date',
            'time_taken', 'technician', 'next_maintenance_date',
            'failure_reason', 'notes', 'status',
        ),
        'users' => array(
            'id', 'username', 'email', 'password_hash', 'role', 'active', 'created_at',
        ),
        'profile' => array(
            'id', 'user_id', 'first_name', 'last_name', 'avatar_url',
        ),
        'ticket_status' => array(
            'id', 'code', 'name', 'sort_order', 'is_terminal', 'active',
        ),
        'tickets' => array(
            'id', 'property_id', 'status_id', 'title', 'description', 'observation',
            'created_by', 'assigned_to', 'created_at', 'updated_at', 'closed_at', 'deleted_at',
        ),
        'ticket_histories' => array(
            'id', 'ticket_id', 'actor_user_id', 'action_type', 'description', 'observation',
            'from_status_id', 'to_status_id', 'created_at',
        ),
        'rendition_reports' => array(
            'id', 'report_code', 'title', 'date_from', 'date_to', 'scope_hash',
            'filters_json', 'parameters_json', 'stats_json', 'maintenance_ids_json',
            'ticket_ids_json', 'content_markdown', 'ai_generated', 'status',
            'created_by', 'approved_by', 'created_at', 'updated_at', 'approved_at',
        ),
    );

    /** @var PropertyRepository|null */
    private $propertyRepository = null;

    /** @var MaintenanceRepository|null */
    private $maintenanceRepository = null;

    /** @var TicketRepository|null */
    private $ticketRepository = null;

    /** @var RenditionReportRepository|null */
    private $renditionReportRepository = null;

    /** @var int */
    private $executedQueries = 0;

    public function __construct($filename = null)
    {
        $resolvedFile = $filename ?: SYSTEC_DB;
        $this->open($resolvedFile);
        $this->busyTimeout(5000);
        $this->exec('PRAGMA foreign_keys = ON');
        $this->ensureSchema($resolvedFile);
    }

    public function properties(): PropertyRepository
    {
        if ($this->propertyRepository === null) {
            $this->propertyRepository = new PropertyRepository($this);
        }

        return $this->propertyRepository;
    }

    public function maintenances(): MaintenanceRepository
    {
        if ($this->maintenanceRepository === null) {
            $this->maintenanceRepository = new MaintenanceRepository($this);
        }

        return $this->maintenanceRepository;
    }

    public function tickets(): TicketRepository
    {
        if ($this->ticketRepository === null) {
            $this->ticketRepository = new TicketRepository($this);
        }

        return $this->ticketRepository;
    }

    public function renditionReports(): RenditionReportRepository
    {
        if ($this->renditionReportRepository === null) {
            $this->renditionReportRepository = new RenditionReportRepository($this);
        }

        return $this->renditionReportRepository;
    }

    public function queryCount(): int
    {
        return $this->executedQueries;
    }

    public function resetQueryCount(): void
    {
        $this->executedQueries = 0;
    }

    private function ensureSchema($dbFile = null)
    {
        // PERF-03: Evita los 6 CREATE TABLE + 5 INSERT de inicio en cada request
        // cuando las tablas ya existen.
        $exists = $this->querySingle(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='users' LIMIT 1"
        );
        if ($exists !== null) {
            return;
        }
        $this->exec('CREATE TABLE IF NOT EXISTS properties (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            grupo STRING,
            subgrupo STRING,
            seccion STRING,
            ident STRING,
            adscription STRING,
            description STRING,
            model STRING,
            color STRING,
            serial STRING,
            status STRING
        )');

        $this->exec('CREATE TABLE IF NOT EXISTS maintenances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id INTEGER,
            code STRING,
            type STRING,
            description STRING,
            date DATETIME,
            time_taken INTEGER,
            technician VARCHAR(255),
            next_maintenance_date DATETIME,
            failure_reason TEXT,
            notes TEXT,
            status STRING
        )');

        $this->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT \'consulta\',
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $this->exec('CREATE TABLE IF NOT EXISTS profile (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            avatar_url TEXT
        )');

        $this->exec('CREATE TABLE IF NOT EXISTS ticket_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_terminal INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1
        )');

        $this->exec('CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            property_id INTEGER NOT NULL,
            status_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            observation TEXT,
            created_by INTEGER NOT NULL,
            assigned_to INTEGER,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME
        )');

        $this->exec('CREATE TABLE IF NOT EXISTS ticket_histories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            actor_user_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            description TEXT NOT NULL,
            observation TEXT,
            from_status_id INTEGER,
            to_status_id INTEGER,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $this->exec("CREATE TABLE IF NOT EXISTS rendition_reports (
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

        $statuses = array(
            array('iniciado', 'Iniciado', 1, 0),
            array('en_progreso', 'En progreso', 2, 0),
            array('con_retraso', 'Con retraso', 3, 0),
            array('en_espera', 'En espera', 4, 0),
            array('finalizado', 'Finalizado', 5, 1),
        );

        foreach ($statuses as $status) {
            $this->exec('INSERT OR IGNORE INTO ticket_status (code, name, sort_order, is_terminal, active) VALUES (\'' . $status[0] . '\', \'' . $status[1] . '\', ' . $status[2] . ', ' . $status[3] . ', 1)');
        }
    }

    private function tableName($table)
    {
        $table = (string) $table;

        if (!array_key_exists($table, self::$schema)) {
            throw new InvalidArgumentException('Tabla no permitida: ' . $table);
        }

        return $table;
    }

    private function columnName($table, $column)
    {
        $table = $this->tableName($table);
        $column = (string) $column;

        if (!in_array($column, self::$schema[$table], true)) {
            throw new InvalidArgumentException('Columna no permitida: ' . $table . '.' . $column);
        }

        return $column;
    }

    private function fieldsSql($table, $fields)
    {
        if ($fields === null || trim((string) $fields) === '' || trim((string) $fields) === '*') {
            return '*';
        }

        $columns = array();
        foreach (explode(',', (string) $fields) as $column) {
            $columns[] = $this->columnName($table, trim($column));
        }

        return implode(', ', $columns);
    }

    public function buildOrderClause($table, $sort): string
    {
        if ($sort === null || trim((string) $sort) === '') {
            return '';
        }

        $sortTerms = array();
        foreach (explode(',', trim((string) $sort)) as $sortTerm) {
            if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+(ASC|DESC))?$/i', trim($sortTerm), $matches)) {
                throw new InvalidArgumentException('Orden no permitido: ' . $sort);
            }

            $column = $this->columnName($table, $matches[1]);
            $direction = isset($matches[2]) ? strtoupper($matches[2]) : 'ASC';
            $sortTerms[] = $column . ' ' . $direction;
        }

        return ' ORDER BY ' . implode(', ', $sortTerms);
    }

    private function whereSql($table, array $conditions, array &$params)
    {
        if (!$conditions) {
            return '';
        }

        $clauses = array();
        foreach ($conditions as $key => $condition) {
            if (is_int($key)) {
                if (!is_array($condition) || count($condition) < 2) {
                    throw new InvalidArgumentException('Condición inválida');
                }

                $column = $condition[0];
                $operator = strtoupper((string) $condition[1]);
                $value = array_key_exists(2, $condition) ? $condition[2] : null;
            } else {
                $column = $key;
                $operator = '=';
                $value = $condition;
            }

            $column = $this->columnName($table, $column);
            $operators = array('=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'IS', 'IS NOT');

            if (!in_array($operator, $operators, true)) {
                throw new InvalidArgumentException('Operador no permitido: ' . $operator);
            }

            $param = ':c' . count($params);
            $clauses[] = $column . ' ' . $operator . ' ' . $param;
            $params[$param] = $value;
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    private function bindValues($stmt, array $params)
    {
        foreach ($params as $name => $value) {
            if ($value === null) {
                $type = SQLITE3_NULL;
            } elseif (is_int($value) || is_bool($value)) {
                $type = SQLITE3_INTEGER;
            } else {
                $type = SQLITE3_TEXT;
            }

            $stmt->bindValue($name, $value, $type);
        }
    }

    public function fetchAllQuery(string $query, array $params = array()): array
    {
        $stmt = $this->prepare($query);

        if (!$stmt) {
            throw new RuntimeException('Error preparando consulta: ' . $this->lastErrorMsg());
        }

        $this->bindValues($stmt, $params);
        $this->executedQueries++;
        $result = $stmt->execute();

        if (!$result) {
            throw new RuntimeException('Error ejecutando consulta: ' . $this->lastErrorMsg());
        }

        $rows = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function create($table, array $data)
    {
        $table = $this->tableName($table);

        if (!$data) {
            throw new InvalidArgumentException('No hay datos para insertar');
        }

        $columns = array();
        $placeholders = array();
        $params = array();

        foreach ($data as $column => $value) {
            $columns[] = $this->columnName($table, $column);
            $param = ':p' . count($params);
            $placeholders[] = $param;
            $params[$param] = $value;
        }

        $query = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->prepare($query);

        if (!$stmt) {
            throw new RuntimeException('Error preparando inserción: ' . $this->lastErrorMsg());
        }

        $this->bindValues($stmt, $params);
        $this->executedQueries++;

        if (!$stmt->execute()) {
            throw new RuntimeException('Error insertando en la base local SysTec (SQLite): ' . $this->lastErrorMsg());
        }

        return $this->lastInsertRowID();
    }

    public function update($table, $id, array $data)
    {
        $table = $this->tableName($table);
        $id = filter_var($id, FILTER_VALIDATE_INT);

        if ($id === false || $id === null) {
            throw new InvalidArgumentException('Identificador inválido');
        }

        if (!$data) {
            return 0;
        }

        $sets = array();
        $params = array(':id' => $id);

        foreach ($data as $column => $value) {
            $column = $this->columnName($table, $column);
            $param = ':p' . count($params);
            $sets[] = $column . ' = ' . $param;
            $params[$param] = $value;
        }

        $query = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->prepare($query);

        if (!$stmt) {
            throw new RuntimeException('Error preparando actualización: ' . $this->lastErrorMsg());
        }

        $this->bindValues($stmt, $params);
        $this->executedQueries++;

        if (!$stmt->execute()) {
            throw new RuntimeException('Error actualizando la base local SysTec (SQLite): ' . $this->lastErrorMsg());
        }

        return $this->changes();
    }

    public function index($table, array $conditions = array(), $fields = '*', $sort = null, $limit = null)
    {
        $table = $this->tableName($table);
        $fields = $this->fieldsSql($table, $fields);
        $params = array();
        $where = $this->whereSql($table, $conditions, $params);
        $order = $this->buildOrderClause($table, $sort);
        $limitSql = '';

        if ($limit !== null) {
            $limitSql = ' LIMIT ' . max(0, (int) $limit);
        }

        $query = 'SELECT ' . $fields . ' FROM ' . $table . $where . $order . $limitSql;

        return $this->fetchAllQuery($query, $params);
    }

    public function countRows($table, array $conditions = array()): int
    {
        $table = $this->tableName($table);
        $params = array();
        $where = $this->whereSql($table, $conditions, $params);
        $stmt = $this->prepare('SELECT COUNT(*) AS row_count FROM ' . $table . $where);

        if (!$stmt) {
            throw new RuntimeException('Error preparando conteo: ' . $this->lastErrorMsg());
        }

        $this->bindValues($stmt, $params);
        $this->executedQueries++;
        $result = $stmt->execute();
        if (!$result) {
            throw new RuntimeException('Error ejecutando conteo: ' . $this->lastErrorMsg());
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);

        return (int) ($row['row_count'] ?? 0);
    }

    /**
     * Solo para SQL estático interno. Nunca pasar entrada de usuario.
     */
    public function select(string $query)
    {
        $this->executedQueries++;
        $stmt = $this->query($query);
        $result = array();

        if ($stmt) {
            while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function getFirstForId($table, $id)
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);

        if ($id === false || $id === null) {
            return null;
        }

        return $this->getFirstForConditions($table, array(array('id', '=', $id)));
    }

    public function getFirstForConditions($table, array $conditions)
    {
        $rows = $this->index($table, $conditions, '*', null, 1);

        return $rows ? $rows[0] : null;
    }

    public function getListRoles()
    {
        return array(
            'admin' => 'Administrador',
            'operador' => 'Operador',
            'tecnico' => 'Técnico',
            'consulta' => 'Consulta',
            'personal' => 'Personal',
        );
    }

    // -------------------------------------------------------------------------
    // Delegación a repositorios especializados (compatibilidad hacia atrás)
    // -------------------------------------------------------------------------

    public function createOrUpdate($dataCsv, bool $preserveExistingStatus = false)
    {
        return $this->properties()->createOrUpdate($dataCsv, $preserveExistingStatus);
    }

    public function syncPropertyAdscriptions(array $properties, array $adscriptionsByIdent): int
    {
        return $this->properties()->syncPropertyAdscriptions($properties, $adscriptionsByIdent);
    }

    public function syncPropertyAssignments(array $properties, array $assignmentsByIdent): int
    {
        return $this->properties()->syncPropertyAssignments($properties, $assignmentsByIdent);
    }

    public function getListProperties($table = 'properties')
    {
        return $this->properties()->getListProperties((string) $table);
    }

    public function getCodeId($id)
    {
        return $this->properties()->getCodeId($id);
    }

    public function getListPropertyStatus()
    {
        return $this->properties()->getListPropertyStatus();
    }

    public function indexWithMaintenances($fields = 'properties.*', $sort = null)
    {
        return $this->maintenances()->indexWithMaintenances($fields, $sort);
    }

    public function maintenanceCountsByProperty(): array
    {
        return $this->maintenances()->maintenanceCountsByProperty();
    }

    public function getListMaintenanceStatus()
    {
        return $this->maintenances()->getListMaintenanceStatus();
    }

    public function getListMaintenanceType()
    {
        return $this->maintenances()->getListMaintenanceType();
    }

    public function getListMaintenanceTechnician()
    {
        return $this->maintenances()->getListMaintenanceTechnician();
    }

    public function restarFechasEnHoras(DateTime $fecha1, DateTime $fecha2)
    {
        return $this->maintenances()->restarFechasEnHoras($fecha1, $fecha2);
    }

    public function ticketCountsByProperty(): array
    {
        return $this->tickets()->ticketCountsByProperty();
    }

    public function getTicketStatuses(): array
    {
        return $this->tickets()->getTicketStatuses();
    }

    public function getTicketStatusByCode(string $code): ?array
    {
        return $this->tickets()->getTicketStatusByCode($code);
    }

    public function ticketHistoryWithActors(int $ticketId): array
    {
        return $this->tickets()->ticketHistoryWithActors($ticketId);
    }

    public function supportTicketStatusCounts(?int $createdBy = null): array
    {
        return $this->tickets()->supportTicketStatusCounts($createdBy);
    }

    public function supportTicketCount(?int $createdBy = null): int
    {
        return $this->tickets()->supportTicketCount($createdBy);
    }

    public function supportTickets(int $limit, int $offset, ?int $createdBy = null): array
    {
        return $this->tickets()->supportTickets($limit, $offset, $createdBy);
    }

    public function personalTicketStatusCounts(int $userId, array $filters = array()): array
    {
        return $this->tickets()->personalTicketStatusCounts($userId, $filters);
    }

    public function personalTicketCount(int $userId, array $filters = array()): int
    {
        return $this->tickets()->personalTicketCount($userId, $filters);
    }

    public function personalTickets(int $userId, array $filters, int $limit, int $offset): array
    {
        return $this->tickets()->personalTickets($userId, $filters, $limit, $offset);
    }

    public function personalTicketDetails(int $ticketId, int $userId): ?array
    {
        return $this->tickets()->personalTicketDetails($ticketId, $userId);
    }

    public function softDeleteTicket(int $ticketId, int $actorUserId, string $deletedAt): bool
    {
        return $this->tickets()->softDeleteTicket($ticketId, $actorUserId, $deletedAt);
    }
}

/** Conexión compartida por request. */
function db(): DB
{
    static $instance = null;

    if ($instance === null) {
        $instance = new DB();
    }

    return $instance;
}
