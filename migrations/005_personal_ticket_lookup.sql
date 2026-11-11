CREATE INDEX IF NOT EXISTS idx_tickets_personal_active_updated
    ON tickets (created_by, deleted_at, updated_at DESC, id DESC);
