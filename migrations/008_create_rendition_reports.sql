CREATE TABLE IF NOT EXISTS rendition_reports (
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
);

CREATE INDEX IF NOT EXISTS idx_rendition_reports_dates
    ON rendition_reports (date_from DESC, date_to DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_rendition_reports_status
    ON rendition_reports (status, updated_at DESC);
