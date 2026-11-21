<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';

require_role(array('admin', 'operador', 'tecnico'));
verify_csrf();

$db = db();
$table = 'maintenances';

if (isset($_POST['register'])) {
    $returnToDashboard = isset($_POST['return_to'])
        && is_string($_POST['return_to'])
        && $_POST['return_to'] === 'dashboard';
    $dashboardReturnUrl = dashboard_return_url($_POST);

    $postText = static function (string $key): string {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    };
    $property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_VALIDATE_INT) : false;
    $ticket_id = isset($_POST['ticket_id']) && $_POST['ticket_id'] !== ''
        ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
        : null;
    $type = $postText('type');
    $description = $postText('description');
    $date = $postText('date');
    $technician = $postText('technician');
    $next_maintenance_date = $postText('next_maintenance_date');
    $failure_reason = $postText('failure_reason');
    $notes = $postText('notes');
    $status = 'iniciado';
    $formValues = array(
        'property_id' => $property_id === false ? '' : (string) $property_id,
        'type' => $type,
        'technician' => $technician,
        'date' => $date,
        'failure_reason' => $failure_reason,
        'description' => $description,
        'notes' => $notes,
        'next_maintenance_date' => $next_maintenance_date,
    );
    $respondWithError = static function (string $message, int $statusCode = 422) use (
        $returnToDashboard,
        $dashboardReturnUrl,
        $formValues
    ): void {
        if ($returnToDashboard) {
            $_SESSION['dashboard_maintenance_feedback'] = array(
                'type' => 'danger',
                'message' => $message,
                'values' => $formValues,
            );
            header('Location: ' . $dashboardReturnUrl);
            exit();
        }

        http_response_code($statusCode);
        exit($message);
    };

    include_once __DIR__ . '/validations/register.php';
    if ($validationError !== null) {
        $respondWithError($validationError);
    }

    $property = $db->getFirstForId('properties', $property_id);
    if (!$property) {
        $respondWithError('El bien seleccionado ya no existe.');
    }

    if ($ticket_id === false) {
        $respondWithError('El ticket seleccionado no es válido.');
    }
    if ($ticket_id !== null) {
        $ticket = $db->getFirstForConditions(
            'tickets',
            array(
                array('id', '=', (int) $ticket_id),
                array('property_id', '=', (int) $property_id),
                array('deleted_at', 'IS', null),
            )
        );
        if (!$ticket) {
            $respondWithError('El ticket seleccionado no existe o no está asociado al bien elegido.');
        }
    }

    if (strtotime($date) === false) {
        $respondWithError('La fecha de inicio no es válida.');
    }

    if ($next_maintenance_date !== '' && strtotime($next_maintenance_date) === false) {
        $respondWithError('La fecha del próximo mantenimiento no es válida.');
    }

    try {
        $code = $db->getCodeId($property_id);
        $data = array(
            'property_id' => $property_id,
            'ticket_id' => $ticket_id,
            'code' => $code,
            'type' => $type,
            'description' => $description,
            'date' => $date,
            'technician' => $technician,
            'next_maintenance_date' => $next_maintenance_date !== '' ? $next_maintenance_date : null,
            'failure_reason' => $failure_reason,
            'notes' => $notes,
            'status' => $status,
        );
        $db->create($table, $data);

        if ($returnToDashboard) {
            $_SESSION['dashboard_maintenance_feedback'] = array(
                'type' => 'success',
                'message' => 'Mantenimiento registrado correctamente.',
                'values' => array(),
            );
            header('Location: ' . $dashboardReturnUrl);
            exit();
        }

        $_SESSION['maintenance_notice'] = 'Mantenimiento registrado correctamente.';
        header('Location: ' . url('maintenances.php'));
        exit();
    } catch (Exception $e) {
        error_log('Error registrando mantenimiento: ' . $e->getMessage());
        $respondWithError('No se pudo registrar el mantenimiento. Revisa los datos e inténtalo nuevamente.', 500);
    }
}

http_response_code(400);
exit('Solicitud inválida.');
