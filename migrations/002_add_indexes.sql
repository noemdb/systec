CREATE INDEX IF NOT EXISTS idx_properties_ident
    ON properties (ident);

CREATE INDEX IF NOT EXISTS idx_maintenances_property
    ON maintenances (property_id);

CREATE INDEX IF NOT EXISTS idx_maintenances_status_next
    ON maintenances (status, next_maintenance_date);
