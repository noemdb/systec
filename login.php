<?php
$title = 'Iniciar sesión';

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

if (auth_check()) {
    $user = auth_user();
    $destination = is_array($user) && isset($user['role']) && $user['role'] === 'personal'
        ? 'personal/index.php'
        : 'dashboard.php';
    header('Location: ' . url($destination));
    exit();
}

$error = '';
$hasUsers = count(db()->index('users', array(), 'id', null, 1)) > 0;
$initialUsername = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
$preCheckThrottle = auth_throttle_check($initialUsername);
$isBlocked = $preCheckThrottle['blocked'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    $throttle = auth_throttle_check($username);

    if ($throttle['blocked']) {
        http_response_code(429);
        $error = 'Demasiados intentos fallidos para esta ' . $throttle['reason'] . '. Por seguridad, el acceso está bloqueado temporalmente. Inténtalo de nuevo en ' . $throttle['retry_minutes'] . ' minuto(s).';
        $isBlocked = true;
    } elseif (auth_login($username, $password)) {
        auth_throttle_clear($username);
        header('Location: ' . auth_intended_url());
        exit();
    } else {
        auth_throttle_record_failure($username);
        $updatedThrottle = auth_throttle_check($username);
        if ($updatedThrottle['blocked']) {
            http_response_code(429);
            $error = 'Demasiados intentos fallidos. El acceso ha sido bloqueado temporalmente por ' . $updatedThrottle['retry_minutes'] . ' minutos.';
            $isBlocked = true;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
            if ($updatedThrottle['remaining'] <= 3 && $updatedThrottle['remaining'] > 0) {
                $error .= ' Te quedan ' . $updatedThrottle['remaining'] . ' intento(s) antes del bloqueo temporal.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es" class="h-100" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/include/head.php'; ?>
</head>
<body class="d-flex h-100 text-center">
    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">
        <main class="px-3 my-auto">
            <div class="card shadow-sm mx-auto" style="max-width: 420px;">
                <div class="card-body text-start p-4">
                    <h1 class="h4 mb-1 text-center">SysTec GMB</h1>
                    <p class="text-body-secondary text-center mb-4">Gestión de mantenimientos de bienes</p>

                    <?php if (!$hasUsers) : ?>
                        <div class="alert alert-warning small">
                            No hay usuarios configurados. Crea el primero desde la terminal:
                            <code>php bin/create-user.php admin admin</code>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== '') : ?>
                        <div class="alert alert-danger small" role="alert"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus
                                   value="<?= e(isset($_POST['username']) ? $_POST['username'] : '') ?>"
                                   <?= $isBlocked ? 'disabled' : '' ?>>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required
                                   <?= $isBlocked ? 'disabled' : '' ?>>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" <?= $isBlocked ? 'disabled' : '' ?>>
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Entrar
                        </button>
                    </form>
                </div>
            </div>
        </main>

        <footer class="mt-auto text-body-secondary small">
            <p class="mb-0">Oficina de Informática</p>
        </footer>
    </div>

    <?php include __DIR__ . '/include/script.php'; ?>
</body>
</html>
