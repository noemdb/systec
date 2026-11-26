<?php
/**
 * Sincroniza los perfiles del personal de SysTec GMB.
 *
 * Uso:
 *   php bin/seed-profiles.php
 *
 * Es idempotente: actualiza first_name y last_name por username, conserva
 * avatar_url y no crea usuarios. Si falta un usuario, revierte toda la carga.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}

if (in_array('--help', array_slice($argv, 1), true) || in_array('-h', array_slice($argv, 1), true)) {
    echo "Uso: php bin/seed-profiles.php\n";
    exit(0);
}

if (count($argv) !== 1) {
    fwrite(STDERR, "Argumento inválido. Uso: php bin/seed-profiles.php\n");
    exit(2);
}

require_once __DIR__ . '/../conn.php';

$profiles = array(
    'abrizzuela' => array('Alberto', 'Brizuela Ochoa'),
    'amaurizio' => array('Angela', 'Maurizio Perez'),
    'brivero' => array('Belkis', 'Rivero Miranda'),
    'doropeza' => array('Daxy', 'Oropeza Asuaje'),
    'dgarcia' => array('Dilcia', 'Garcia Gonzalez'),
    'hsanchez' => array('Helen', 'Sanchez Mauricio'),
    'hperozo' => array('Hillary', 'Perozo Montes'),
    'jguevara' => array('Juan', 'Guevara Sanchez'),
    'jpino' => array('Julio', 'Pino Escarra'),
    'lperaza' => array('Leonardo', 'Peraza Castillo'),
    'loropeza' => array('Lizmareth', 'Oropeza Asuaje'),
    'mrojas' => array('Maria', 'Rojas Parra'),
    'mtagliaferro' => array('Maria', 'Tagliaferro Aldama'),
    'mseguirit' => array('Mirian', 'Seguerit Alcalde'),
    'ndominguez' => array('Noe', 'Dominguez Brito'),
    'orodriguez' => array('Osvaldo', 'Rodriguez'),
    'rguevara' => array('Rene', 'Guevara Alejos'),
    'rojeda' => array('Reysi', 'Ojeda Torres'),
    'yojeda' => array('Yajaira', 'Ojeda Aviles'),
    'ymarin' => array('Yamileth', 'Marin Castro'),
    'yparadas' => array('Yarait', 'Paradas Castillo'),
    'znadal' => array('Zenaida', 'Nadal Vargas'),
    'zrivas' => array('Zuleima', 'Rivas Vasquez'),
);

$db = null;
$transactionStarted = false;

try {
    $db = db();
    $db->enableExceptions(true);

    $tableExists = $db->querySingle(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'profile' LIMIT 1"
    );
    if ($tableExists === null) {
        throw new RuntimeException('La tabla profile no existe. Ejecuta primero las migraciones.');
    }

    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new RuntimeException('No se pudo iniciar la transacción: ' . $db->lastErrorMsg());
    }
    $transactionStarted = true;

    $findUser = $db->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $upsertProfile = $db->prepare(
        'INSERT INTO profile (user_id, first_name, last_name, avatar_url)
         VALUES (:user_id, :first_name, :last_name, NULL)
         ON CONFLICT(user_id) DO UPDATE SET
             first_name = excluded.first_name,
             last_name = excluded.last_name'
    );

    foreach ($profiles as $username => $names) {
        $findUser->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $findUser->execute();
        $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
        if ($result) {
            $result->finalize();
        }
        if (!$user) {
            throw new RuntimeException('No existe el usuario personal: ' . $username);
        }

        $upsertProfile->bindValue(':user_id', (int) $user['id'], SQLITE3_INTEGER);
        $upsertProfile->bindValue(':first_name', $names[0], SQLITE3_TEXT);
        $upsertProfile->bindValue(':last_name', $names[1], SQLITE3_TEXT);
        $result = $upsertProfile->execute();
        if (!$result) {
            throw new RuntimeException('No se pudo guardar el perfil de ' . $username);
        }
        $result->finalize();
    }

    $findUser->close();
    $upsertProfile->close();

    if (!$db->exec('COMMIT')) {
        throw new RuntimeException('No se pudo confirmar la operación: ' . $db->lastErrorMsg());
    }
    $transactionStarted = false;

    echo 'Perfiles procesados: ' . count($profiles) . "\n";
    echo "La sincronización terminó correctamente.\n";
    $db->close();
} catch (Throwable $e) {
    if ($transactionStarted && $db instanceof DB) {
        $db->exec('ROLLBACK');
    }
    if ($db instanceof DB) {
        $db->close();
    }
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
