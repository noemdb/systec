ALTER TABLE maintenances ADD COLUMN ticket_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_maintenances_ticket_id
    ON maintenances (ticket_id);
