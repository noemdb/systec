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
    $returnQuery = array();
    $allowedReturnFilters = array(
        'category' => array('informatica', 'redes', 'otros'),
        'status' => array('', '__attention__', 'BUENO', 'REGULAR', 'MALO', 'MUY MALO'),
    );
    foreach ($allowedReturnFilters as $filter => $allowedValues) {
        $postKey = 'return_' . $filter;
        if (isset($_POST[$postKey]) && is_string($_POST[$postKey])) {
            $value = trim($_POST[$postKey]);
            if ($value !== '' && in_array($value, $allowedValues, true)) {
                $returnQuery[$filter] = $value;
            }
        }
    }
    if (isset($_POST['return_adscription'])
        && is_string($_POST['return_adscription'])
        && preg_match('/^[A-Za-z0-9_.:\/-]{1,128}$/D', $_POST['return_adscription'])) {
        $returnQuery['adscription'] = $_POST['return_adscription'];
    }
    if (isset($_POST['return_search']) && is_string($_POST['return_search'])) {
        $search = trim($_POST['return_search']);
        if ($search !== '') {
            $returnQuery['search'] = $search;
        }
    }
    $dashboardReturnUrl = url('dashboard.php')
        . ($returnQuery ? '?' . http_build_query($returnQuery) : '');

    $postText = static function (string $key): string {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    };
    $property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_VALIDATE_INT) : false;
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

        header('Location: ' . url('maintenances.php'));
        exit();
    } catch (Exception $e) {
        error_log('Error registrando mantenimiento: ' . $e->getMessage());
        $respondWithError('No se pudo registrar el mantenimiento. Revisa los datos e inténtalo nuevamente.', 500);
    }
}

http_response_code(400);
exit('Solicitud inválida.');
