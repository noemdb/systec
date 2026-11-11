<?php
/**
 * Crea o actualiza un usuario de SysTec GMB.
 *
 * Uso:
 *   php bin/create-user.php <usuario> <rol> [contraseña]
 *
 * Roles: admin, operador, tecnico, consulta, personal.
 * Si se omite la contraseña, se genera una aleatoria y se imprime una sola vez.
 * personal
 * php bin/create-user.php personal personal personal
 */

require_once __DIR__ . '/../conn.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI');
}

if ($argc < 3) {
    fwrite(STDERR, "Uso: php bin/create-user.php <usuario> <rol> [contraseña]\n");
    fwrite(STDERR, "Roles: admin, operador, tecnico, consulta, personal\n");
    exit(1);
}

$username = trim((string) $argv[1]);
$role = trim((string) $argv[2]);
$password = isset($argv[3]) ? (string) $argv[3] : bin2hex(random_bytes(8));
$roles = db()->getListRoles();

if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username)) {
    fwrite(STDERR, "Usuario inválido. Usa 3-64 caracteres alfanuméricos, punto, guion o guion bajo.\n");
    exit(1);
}

if (!array_key_exists($role, $roles)) {
    fwrite(STDERR, "Rol inválido. Roles: " . implode(', ', array_keys($roles)) . "\n");
    exit(1);
}

if (strlen($password) < 6) {
    fwrite(STDERR, "La contraseña debe tener al menos 6 caracteres.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = db()->getFirstForConditions('users', array('username' => $username));

if ($existing) {
    $stmt = db()->prepare('UPDATE users SET password_hash = :hash, role = :role, active = 1 WHERE id = :id');
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    $stmt->bindValue(':id', (int) $existing['id'], SQLITE3_INTEGER);
    $stmt->execute();
    $action = 'actualizado';
} else {
    $stmt = db()->prepare('INSERT INTO users (username, password_hash, role, active) VALUES (:username, :hash, :role, 1)');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    $stmt->execute();
    $action = 'creado';
}

echo "Usuario {$action}: {$username}\n";
echo "Rol: {$role}\n";
echo "Contraseña: {$password}\n";
echo "Guarda la contraseña en un lugar seguro. No se vuelve a mostrar.\n";
