<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class PersonalUserSeedMigrationTest extends TestCase
{
    private string $tempDir;
    private string $databaseFile;
    private string $migrationFile;
    private array $expectedUsernames = array(
        'abrizzuela', 'amaurizio', 'brivero', 'doropeza', 'dgarcia', 'hsanchez',
        'hperozo', 'jguevara', 'jpino', 'lperaza', 'loropeza', 'mrojas',
        'mtagliaferro', 'mseguirit', 'ndominguez', 'orodriguez', 'rguevara', 'rojeda',
        'yojeda', 'ymarin', 'yparadas', 'znadal', 'zrivas',
    );

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_personal_users_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0750, true);
        $this->databaseFile = $this->tempDir . '/db.db';
        $this->migrationFile = realpath(__DIR__ . '/../../migrations/009_seed_personal_users.sql');
        self::assertNotFalse($this->migrationFile);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: array() as $path) {
            @unlink($path);
        }
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function testCreatesPersonalAccountsWithDerivedEmailPasswordAndInactiveState(): void
    {
        $db = new DB($this->databaseFile);
        $db->close();
        $legacyId = $this->createLegacyAccount('admin', 'hash-preexistente', 'admin', 1);

        $this->applyMigration();
        $db = new SQLite3($this->databaseFile, SQLITE3_OPEN_READONLY);

        try {
            self::assertSame('ok', $db->querySingle('PRAGMA integrity_check'));
            $columnsResult = $db->query('PRAGMA table_info(users)');
            $columns = array();
            while (($column = $columnsResult->fetchArray(SQLITE3_ASSOC)) !== false) {
                $columns[] = $column['name'];
            }
            $columnsResult->finalize();
            self::assertContains('email', $columns);
            self::assertSame(24, (int) $db->querySingle('SELECT COUNT(*) FROM users'));
            self::assertSame(23, (int) $db->querySingle(
                "SELECT COUNT(*) FROM users WHERE role = 'personal' AND email LIKE '%@cmsf.gob.ve'"
            ));
            self::assertSame(22, (int) $db->querySingle(
                "SELECT COUNT(*) FROM users WHERE role = 'personal' AND active = 1"
            ));

            $statement = $db->prepare(
                'SELECT username, password_hash, role, active, email FROM users WHERE username = :username'
            );
            foreach ($this->expectedUsernames as $username) {
                $statement->bindValue(':username', $username, SQLITE3_TEXT);
                $result = $statement->execute();
                $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
                if ($result) {
                    $result->finalize();
                }

                self::assertIsArray($user, 'Falta la cuenta ' . $username);
                self::assertSame($username . '@cmsf.gob.ve', $user['email']);
                self::assertSame('personal', $user['role']);
                self::assertTrue(password_verify('cmsf', (string) $user['password_hash']));
                self::assertSame($username === 'zrivas' ? 0 : 1, (int) $user['active']);
            }
            $statement->close();

            $this->assertLegacyAccountUntouched($db, $legacyId, 'admin', 'admin', 1);
        } finally {
            $db->close();
        }
    }

    public function testPreservesAnExistingUsernameWithoutUpdatingIt(): void
    {
        $db = new DB($this->databaseFile);
        $db->close();
        $legacyId = $this->createLegacyAccount('abrizzuela', 'hash-preexistente', 'consulta', 1);

        $this->applyMigration();
        $db = new SQLite3($this->databaseFile, SQLITE3_OPEN_READONLY);

        try {
            self::assertSame(23, (int) $db->querySingle('SELECT COUNT(*) FROM users'));
            self::assertSame(22, (int) $db->querySingle(
                "SELECT COUNT(*) FROM users WHERE role = 'personal'"
            ));
            $this->assertLegacyAccountUntouched($db, $legacyId, 'abrizzuela', 'consulta', 1);
            self::assertNull($db->querySingle('SELECT email FROM users WHERE id = ' . $legacyId));
        } finally {
            $db->close();
        }
    }

    private function createLegacyAccount(
        string $username,
        string $passwordHash,
        string $role,
        int $active
    ): int {
        $db = new SQLite3($this->databaseFile);
        $statement = $db->prepare(
            'INSERT INTO users (username, password_hash, role, active) VALUES (:username, :hash, :role, :active)'
        );
        $statement->bindValue(':username', $username, SQLITE3_TEXT);
        $statement->bindValue(':hash', $passwordHash, SQLITE3_TEXT);
        $statement->bindValue(':role', $role, SQLITE3_TEXT);
        $statement->bindValue(':active', $active, SQLITE3_INTEGER);
        $result = $statement->execute();
        self::assertNotFalse($result);
        $result->finalize();
        $id = (int) $db->lastInsertRowID();
        $statement->close();
        $db->close();

        return $id;
    }

    private function applyMigration(): void
    {
        $db = new SQLite3($this->databaseFile);
        $db->enableExceptions(true);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('BEGIN IMMEDIATE');

        try {
            $sql = file_get_contents($this->migrationFile);
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

    private function assertLegacyAccountUntouched(
        SQLite3 $db,
        int $legacyId,
        string $expectedUsername,
        string $expectedRole,
        int $expectedActive
    ): void
    {
        $statement = $db->prepare('SELECT * FROM users WHERE id = :id');
        $statement->bindValue(':id', $legacyId, SQLITE3_INTEGER);
        $result = $statement->execute();
        $legacy = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
        if ($result) {
            $result->finalize();
        }
        $statement->close();

        self::assertIsArray($legacy);
        self::assertSame($expectedUsername, $legacy['username']);
        self::assertSame('hash-preexistente', $legacy['password_hash']);
        self::assertSame($expectedRole, $legacy['role']);
        self::assertSame($expectedActive, (int) $legacy['active']);
    }
}