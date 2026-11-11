ALTER TABLE tickets ADD COLUMN deleted_at DATETIME;

CREATE INDEX IF NOT EXISTS idx_tickets_deleted_updated
    ON tickets (deleted_at, updated_at DESC);
