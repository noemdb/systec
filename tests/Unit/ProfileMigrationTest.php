<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class ProfileMigrationTest extends TestCase
{
    private string $tempDir;
    private string $databaseFile;
    private array $expectedProfiles = array(
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_profile_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0750, true);
        $this->databaseFile = $this->tempDir . '/db.db';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: array() as $path) {
            @unlink($path);
        }
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function testMigrationCreatesAndSeedsOneProfilePerPersonalUser(): void
    {
        $db = new SQLite3($this->databaseFile);
        $db->enableExceptions(true);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT \'consulta\',
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $db->exec("INSERT INTO users (username, password_hash, role, active) VALUES ('admin', 'hash', 'admin', 1)");
        foreach (array_keys($this->expectedProfiles) as $username) {
            $db->exec("INSERT INTO users (username, password_hash, role, active) VALUES ('{$username}', 'hash', 'personal', 1)");
        }
        $db->close();

        $this->applyMigration('009_seed_personal_users.sql');
        $this->applyMigration('010_create_profile.sql');
        $db = new SQLite3($this->databaseFile, SQLITE3_OPEN_READONLY);

        try {
            self::assertSame('ok', $db->querySingle('PRAGMA integrity_check'));
            self::assertSame(count($this->expectedProfiles), (int) $db->querySingle('SELECT COUNT(*) FROM profile'));
            $statement = $db->prepare(
                'SELECT users.username, profile.first_name, profile.last_name, profile.avatar_url
                 FROM profile INNER JOIN users ON users.id = profile.user_id
                 WHERE users.username = :username'
            );
            foreach ($this->expectedProfiles as $username => $names) {
                $statement->bindValue(':username', $username, SQLITE3_TEXT);
                $result = $statement->execute();
                $profile = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
                if ($result) {
                    $result->finalize();
                }

                self::assertIsArray($profile, 'Falta el perfil de ' . $username);
                self::assertSame($names[0], $profile['first_name']);
                self::assertSame($names[1], $profile['last_name']);
                self::assertNull($profile['avatar_url']);
            }
            $statement->close();
        } finally {
            $db->close();
        }
    }

    public function testProfileSupportsCrudAndRejectsDuplicateUsers(): void
    {
        $db = new DB(':memory:');

        try {
            $userId = (int) $db->create('users', array(
                'username' => 'profile_test',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $profileId = (int) $db->create('profile', array(
                'user_id' => $userId,
                'first_name' => 'Noe',
                'last_name' => 'Dominguez Brito',
                'avatar_url' => 'https://cmsf.gob.ve/avatars/noe.png',
            ));

            $profile = $db->getFirstForId('profile', $profileId);
            self::assertSame($userId, (int) $profile['user_id']);
            self::assertSame('Noe', $profile['first_name']);
            self::assertSame('Dominguez Brito', $profile['last_name']);
            self::assertSame('https://cmsf.gob.ve/avatars/noe.png', $profile['avatar_url']);

            self::assertSame(1, $db->update('profile', $profileId, array(
                'avatar_url' => 'https://cmsf.gob.ve/avatars/noe-nuevo.png',
            )));
            self::assertSame(
                'https://cmsf.gob.ve/avatars/noe-nuevo.png',
                $db->getFirstForId('profile', $profileId)['avatar_url']
            );

            try {
                $db->create('profile', array(
                    'user_id' => $userId,
                    'first_name' => 'Duplicado',
                    'last_name' => 'No Permitido',
                ));
                self::fail('La restricción UNIQUE debería impedir un segundo perfil para el mismo usuario.');
            } catch (Throwable $e) {
                self::assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
            }
        } finally {
            $db->close();
        }
    }

    public function testDeletingUserAlsoDeletesProfile(): void
    {
        $db = new DB(':memory:');

        try {
            $userId = (int) $db->create('users', array(
                'username' => 'cascade_test',
                'password_hash' => 'hash',
                'role' => 'personal',
            ));
            $profileId = (int) $db->create('profile', array(
                'user_id' => $userId,
                'first_name' => 'Prueba',
                'last_name' => 'Cascada',
            ));

            self::assertTrue($db->exec('DELETE FROM users WHERE id = ' . $userId));
            self::assertNull($db->getFirstForId('profile', $profileId));
        } finally {
            $db->close();
        }
    }

    private function applyMigration(string $filename): void
    {
        $path = realpath(__DIR__ . '/../../migrations/' . $filename);
        self::assertNotFalse($path);
        $db = new SQLite3($this->databaseFile);
        $db->enableExceptions(true);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('BEGIN IMMEDIATE');

        try {
            $sql = file_get_contents($path);
            self::assertIsString($sql);
            self::assertTrue($db->exec($sql));
            self::assertTrue($db->exec('COMMIT'));
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        } finally {
            $db->close();
        }
    }
}
