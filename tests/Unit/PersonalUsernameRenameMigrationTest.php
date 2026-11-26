<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class PersonalUsernameRenameMigrationTest extends TestCase
{
    private string $tempDir;
    private string $databaseFile;
    private array $renames = array(
        'arafael' => 'abrizzuela',
        'amaria' => 'amaurizio',
        'bmaria' => 'brivero',
        'dalided' => 'doropeza',
        'dmariela' => 'dgarcia',
        'halejandra' => 'hsanchez',
        'hnazareth' => 'hperozo',
        'jcarlos' => 'jguevara',
        'jalfredo' => 'jpino',
        'lmanuel' => 'lperaza',
        'lyetzabeth' => 'loropeza',
        'mangelica' => 'mrojas',
        'mauxiliadora' => 'mtagliaferro',
        'mlourdes' => 'mseguirit',
        'nmiguel' => 'ndominguez',
        'oanival' => 'orodriguez',
        'rrafael' => 'rguevara',
        'rnacarith' => 'rojeda',
        'ymaribel' => 'yojeda',
        'ydel' => 'ymarin',
        'ymarcela' => 'yparadas',
        'zmercedes' => 'znadal',
        'zjosefina' => 'zrivas',
    );

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_username_rename_' . bin2hex(random_bytes(8));
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

    public function testRenamesAccountsAndEmailsWithoutChangingIdsOrProfiles(): void
    {
        $db = new SQLite3($this->databaseFile);
        $db->enableExceptions(true);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            active INTEGER NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $db->exec('CREATE TABLE profile (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            avatar_url TEXT
        )');
        $db->exec("INSERT INTO users (username, password_hash, role, active) VALUES ('admin', 'admin-hash', 'admin', 1)");

        $original = array();
        foreach ($this->renames as $oldUsername => $newUsername) {
            $active = $oldUsername === 'zjosefina' ? 0 : 1;
            $db->exec(
                "INSERT INTO users (username, email, password_hash, role, active) VALUES "
                . "('{$oldUsername}', '{$oldUsername}@cmsf.gob.ve', 'hash-{$oldUsername}', 'personal', {$active})"
            );
            $userId = (int) $db->lastInsertRowID();
            $db->exec(
                "INSERT INTO profile (user_id, first_name, last_name, avatar_url) VALUES "
                . "({$userId}, 'Nombre', 'Apellidos', 'https://cmsf.gob.ve/avatar.png')"
            );
            $original[$oldUsername] = array(
                'id' => $userId,
                'active' => $active,
                'password_hash' => 'hash-' . $oldUsername,
            );
        }
        $db->close();

        $this->applyMigration();
        $db = new SQLite3($this->databaseFile, SQLITE3_OPEN_READONLY);

        try {
            self::assertSame('ok', $db->querySingle('PRAGMA integrity_check'));
            self::assertSame(count($this->renames) + 1, (int) $db->querySingle('SELECT COUNT(*) FROM users'));
            self::assertSame(count($this->renames), (int) $db->querySingle('SELECT COUNT(*) FROM profile'));

            foreach ($this->renames as $oldUsername => $newUsername) {
                $expected = $original[$oldUsername];
                $statement = $db->prepare(
                    'SELECT users.*, profile.id AS profile_id, profile.first_name, profile.last_name, profile.avatar_url
                     FROM users INNER JOIN profile ON profile.user_id = users.id
                     WHERE users.id = :id'
                );
                $statement->bindValue(':id', $expected['id'], SQLITE3_INTEGER);
                $result = $statement->execute();
                $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
                if ($result) {
                    $result->finalize();
                }
                $statement->close();

                self::assertIsArray($user, 'Se perdió el usuario ' . $oldUsername);
                self::assertSame($newUsername, $user['username']);
                self::assertSame($newUsername . '@cmsf.gob.ve', $user['email']);
                self::assertSame($expected['password_hash'], $user['password_hash']);
                self::assertSame('personal', $user['role']);
                self::assertSame($expected['active'], (int) $user['active']);
                self::assertSame('Nombre', $user['first_name']);
                self::assertSame('Apellidos', $user['last_name']);
                self::assertSame('https://cmsf.gob.ve/avatar.png', $user['avatar_url']);
                self::assertNull($db->querySingle(
                    "SELECT id FROM users WHERE username = '{$oldUsername}'"
                ));
            }

            self::assertSame('admin', $db->querySingle("SELECT username FROM users WHERE username = 'admin'"));
            self::assertNull($db->querySingle("SELECT id FROM users WHERE username = 'fsequera'"));
            self::assertNull($db->querySingle("SELECT id FROM users WHERE username = 'fcamacho'"));
            self::assertNull($db->querySingle("SELECT id FROM users WHERE username = 'mgarcia'"));
        } finally {
            $db->close();
        }

        $this->applyMigration();
        $db = new SQLite3($this->databaseFile, SQLITE3_OPEN_READONLY);
        try {
            self::assertSame(count($this->renames) + 1, (int) $db->querySingle('SELECT COUNT(*) FROM users'));
            self::assertSame(count($this->renames), (int) $db->querySingle('SELECT COUNT(*) FROM profile'));
        } finally {
            $db->close();
        }
    }

    private function applyMigration(): void
    {
        $path = realpath(__DIR__ . '/../../migrations/011_rename_personal_usernames.sql');
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
