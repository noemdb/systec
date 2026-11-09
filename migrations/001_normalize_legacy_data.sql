UPDATE properties
SET status = 'BUENO'
WHERE status = 'MUY BUENO';

UPDATE maintenances
SET status = 'finalizado'
WHERE status = 'FINALIZADO';

UPDATE maintenances
SET technician = 'ndominguez'
WHERE technician = 'ndomiguez';

UPDATE maintenances
SET date = '2017-05-11 09:00:00'
WHERE date = '2017-05-11 9:00:00';

UPDATE maintenances
SET date = datetime(date)
WHERE date IS NOT NULL AND trim(date) <> '';

UPDATE maintenances
SET next_maintenance_date = NULL
WHERE next_maintenance_date IS NOT NULL AND trim(next_maintenance_date) = '';

UPDATE maintenances
SET next_maintenance_date = datetime(next_maintenance_date)
WHERE next_maintenance_date IS NOT NULL;

CREATE TRIGGER validate_properties_status_insert
BEFORE INSERT ON properties
WHEN NEW.status IS NULL OR trim(NEW.status) NOT IN ('BUENO', 'REGULAR', 'MALO', 'MUY MALO')
BEGIN
    SELECT RAISE(ABORT, 'estado de bien inválido');
END;

CREATE TRIGGER validate_properties_status_update
BEFORE UPDATE OF status ON properties
WHEN NEW.status IS NULL OR trim(NEW.status) NOT IN ('BUENO', 'REGULAR', 'MALO', 'MUY MALO')
BEGIN
    SELECT RAISE(ABORT, 'estado de bien inválido');
END;

CREATE TRIGGER validate_maintenance_status_insert
BEFORE INSERT ON maintenances
WHEN NEW.status IS NULL OR trim(NEW.status) NOT IN ('iniciado', 'revision', 'finalizado')
BEGIN
    SELECT RAISE(ABORT, 'estado de mantenimiento inválido');
END;

CREATE TRIGGER validate_maintenance_status_update
BEFORE UPDATE OF status ON maintenances
WHEN NEW.status IS NULL OR trim(NEW.status) NOT IN ('iniciado', 'revision', 'finalizado')
BEGIN
    SELECT RAISE(ABORT, 'estado de mantenimiento inválido');
END;

CREATE TRIGGER validate_maintenance_transition
BEFORE UPDATE OF status ON maintenances
WHEN OLD.status <> NEW.status
    AND NOT (
        (OLD.status = 'iniciado' AND NEW.status IN ('revision', 'finalizado'))
        OR (OLD.status = 'revision' AND NEW.status = 'finalizado')
        OR (OLD.status = 'finalizado' AND NEW.status = 'revision')
    )
BEGIN
    SELECT RAISE(ABORT, 'transición de estado de mantenimiento inválida');
END;

CREATE TRIGGER validate_maintenance_type_insert
BEFORE INSERT ON maintenances
WHEN NEW.type IS NULL OR trim(NEW.type) NOT IN ('preventivo', 'correctivo', 'predictivo', 'evaluativo')
BEGIN
    SELECT RAISE(ABORT, 'tipo de mantenimiento inválido');
END;

CREATE TRIGGER validate_maintenance_type_update
BEFORE UPDATE OF type ON maintenances
WHEN NEW.type IS NULL OR trim(NEW.type) NOT IN ('preventivo', 'correctivo', 'predictivo', 'evaluativo')
BEGIN
    SELECT RAISE(ABORT, 'tipo de mantenimiento inválido');
END;
