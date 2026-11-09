<?php
$title = 'Iniciar sesión';

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

if (auth_check()) {
    header('Location: ' . url('dashboard.php'));
    exit();
}

$error = '';
$hasUsers = count(db()->index('users', array(), 'id', null, 1)) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if (auth_login($username, $password)) {
        header('Location: ' . auth_intended_url());
        exit();
    }

    $error = 'Usuario o contraseña incorrectos.';
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
                    <p class="text-muted text-center mb-4">Gestión de mantenimientos de bienes</p>

                    <?php if (!$hasUsers) : ?>
                        <div class="alert alert-warning small">
                            No hay usuarios configurados. Crea el primero desde la terminal:
                            <code>php bin/create-user.php admin admin</code>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== '') : ?>
                        <div class="alert alert-danger small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus
                                   value="<?= e(isset($_POST['username']) ? $_POST['username'] : '') ?>">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>
                </div>
            </div>
        </main>

        <footer class="mt-auto text-muted small">
            <p class="mb-0">Oficina de Informática</p>
        </footer>
    </div>

    <?php include __DIR__ . '/include/script.php'; ?>
</body>
</html>
