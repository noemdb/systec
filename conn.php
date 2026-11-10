<?php

date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/security.php';
require_once __DIR__ . '/include/helpers.php';

/**
 * Capa de datos de SysTec GMB.
 *
 * Reglas:
 * - Los nombres de tabla/columna se validan contra listas blancas.
 * - Los valores siempre viajan como parámetros vinculados.
 * - La conexión no se cierra dentro de los métodos; la cierra el llamador
 *   o el final del request.
 */
class DB extends SQLite3
{
    private static $schema = array(
        'properties' => array(
            'id', 'grupo', 'subgrupo', 'seccion', 'ident', 'adscription',
            'description', 'model', 'color', 'serial', 'status',
        ),
        'maintenances' => array(
            'id', 'property_id', 'code', 'type', 'description', 'date',
            'time_taken', 'technician', 'next_maintenance_date',
            'failure_reason', 'notes', 'status',
        ),
        'users' => array(
            'id', 'username', 'password_hash', 'role', 'active', 'created_at',
        ),
        'ticket_status' => array(
            'id', 'code', 'name', 'sort_order', 'is_terminal', 'active',
        ),
        'tickets' => array(
            'id', 'property_id', 'status_id', 'title', 'description', 'observation',
            'created_by', 'assigned_to', 'created_at', 'updated_at', 'closed_at',
        ),
        'ticket_histories' => array(
            'id', 'ticket_id', 'actor_user_id', 'action_type', 'description', 'observation',
            'from_status_id', 'to_status_id', 'created_at',
        ),
    );

    public function __construct($filename = null)
    {
        $this->open($filename ?: SYSTEC_DB);
        $this->busyTimeout(5000);
        $this->exec('PRAGMA foreign_keys = ON');
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
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

    private function orderSql($table, $sort)
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
            $operators = array('=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE');

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

    private function fetchAll($query, array $params = array())
    {
        $stmt = $this->prepare($query);

        if (!$stmt) {
            throw new RuntimeException('Error preparando consulta: ' . $this->lastErrorMsg());
        }

        $this->bindValues($stmt, $params);
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

    private function formatCode($row)
    {
        if (!$row) {
            return null;
        }

        return property_code($row);
    }

    public function createOrUpdate($dataCsv, bool $preserveExistingStatus = false)
    {
        if (!is_array($dataCsv) || count($dataCsv) < 11) {
            throw new InvalidArgumentException('Fila CSV incompleta');
        }

        $ident = str_pad(trim((string) $dataCsv[3]), 4, '0', STR_PAD_LEFT);
        $property = $this->getFirstForConditions('properties', array('ident' => $ident));

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
            $this->create('properties', $data);
            return 'inserted';
        } else {
            if ($preserveExistingStatus
                && (string) $property['status'] !== trim((string) $dataCsv[10])) {
                return 'preserved';
            }
            if ((string) $property['status'] === trim((string) $dataCsv[10])) {
                return 'unchanged';
            }
            $this->update('properties', $property['id'], array('status' => $dataCsv[10]));
            return 'updated';
        }
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

        if (!$stmt->execute()) {
            throw new RuntimeException('Error actualizando la base local SysTec (SQLite): ' . $this->lastErrorMsg());
        }

        return $this->changes();
    }

    public function indexWithMaintenances($fields = 'properties.*', $sort = null)
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

        $query .= $this->orderSql('maintenances', $sort);

        $stmt = $this->query($query);
        $rows = array();

        if ($stmt) {
            while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function index($table, array $conditions = array(), $fields = '*', $sort = null, $limit = null)
    {
        $table = $this->tableName($table);
        $fields = $this->fieldsSql($table, $fields);
        $params = array();
        $where = $this->whereSql($table, $conditions, $params);
        $order = $this->orderSql($table, $sort);
        $limitSql = '';

        if ($limit !== null) {
            $limitSql = ' LIMIT ' . max(0, (int) $limit);
        }

        $query = 'SELECT ' . $fields . ' FROM ' . $table . $where . $order . $limitSql;

        return $this->fetchAll($query, $params);
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
        $result = $stmt->execute();
        if (!$result) {
            throw new RuntimeException('Error ejecutando conteo: ' . $this->lastErrorMsg());
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);

        return (int) ($row['row_count'] ?? 0);
    }

    public function maintenanceCountsByProperty(): array
    {
        $rows = $this->fetchAll(
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

    public function getListProperties($table = 'properties')
    {
        $rows = $this->index($table, array(), '*', 'id ASC');
        $list = array();

        foreach ($rows as $row) {
            $list[$row['id']] = $this->formatCode($row);
        }

        return $list;
    }

    public function getCodeId($id)
    {
        $row = $this->getFirstForId('properties', $id);

        return $this->formatCode($row);
    }

    /**
     * Solo para SQL estático interno. Nunca pasar entrada de usuario.
     */
    public function select(string $query)
    {
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

    public function getListPropertyStatus()
    {
        return array('bueno' => 'Bueno', 'regular' => 'Regular', 'malo' => 'Malo', 'muy malo' => 'Muy malo');
    }

    public function getListMaintenanceStatus()
    {
        return array('iniciado' => 'Iniciado', 'revision' => 'Revisión', 'finalizado' => 'Finalizado');
    }

    public function getListMaintenanceType()
    {
        return array(
            'preventivo' => 'Preventivo',
            'correctivo' => 'Correctivo',
            'predictivo' => 'Predictivo',
            'evaluativo' => 'Evaluativo',
        );
    }

    public function getListMaintenanceTechnician()
    {
        return array('jmedina' => 'jmedina', 'ndominguez' => 'ndominguez');
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

    public function getTicketStatuses(): array
    {
        $rows = $this->index('ticket_status', array('active' => 1), '*', 'sort_order ASC');
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

        $row = $this->getFirstForConditions('ticket_status', array(array('code', '=', $code), array('active', '=', 1)));
        return $row ?: null;
    }

    public function restarFechasEnHoras(DateTime $fecha1, DateTime $fecha2)
    {
        return (int) round(abs($fecha1->getTimestamp() - $fecha2->getTimestamp()) / 3600);
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
