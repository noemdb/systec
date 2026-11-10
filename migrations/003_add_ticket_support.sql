CREATE TABLE IF NOT EXISTS ticket_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_terminal INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS tickets (
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
);

CREATE TABLE IF NOT EXISTS ticket_histories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    actor_user_id INTEGER NOT NULL,
    action_type TEXT NOT NULL,
    description TEXT NOT NULL,
    observation TEXT,
    from_status_id INTEGER,
    to_status_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO ticket_status (code, name, sort_order, is_terminal, active)
VALUES
    ('iniciado', 'Iniciado', 1, 0, 1),
    ('en_progreso', 'En progreso', 2, 0, 1),
    ('con_retraso', 'Con retraso', 3, 0, 1),
    ('en_espera', 'En espera', 4, 0, 1),
    ('finalizado', 'Finalizado', 5, 1, 1);

CREATE INDEX IF NOT EXISTS idx_tickets_status_updated
    ON tickets (status_id, updated_at DESC);

CREATE INDEX IF NOT EXISTS idx_tickets_created_by_updated
    ON tickets (created_by, updated_at DESC);

CREATE INDEX IF NOT EXISTS idx_tickets_property_created
    ON tickets (property_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_ticket_histories_ticket_created
    ON ticket_histories (ticket_id, created_at ASC, id ASC);
