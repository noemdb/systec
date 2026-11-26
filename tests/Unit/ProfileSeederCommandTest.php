<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class ProfileSeederCommandTest extends TestCase
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
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'systec_profile_seeder_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0750, true);
        $this->databaseFile = $this->tempDir . '/db.db';

        $db = new DB($this->databaseFile);
        foreach (array_keys($this->expectedProfiles) as $username) {
            $db->create('users', array(
                'username' => $username,
                'password_hash' => 'hash',
                'role' => 'personal',
                'active' => $username === 'zrivas' ? 0 : 1,
            ));
        }
        $db->close();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: array() as $path) {
            @unlink($path);
        }
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function testSeederIsIdempotentAndPreservesAvatarUrls(): void
    {
        $db = new DB($this->databaseFile);
        $noe = $db->getFirstForConditions('users', array('username' => 'ndominguez'));
        $db->create('profile', array(
            'user_id' => (int) $noe['id'],
            'first_name' => 'Nombre Incorrecto',
            'last_name' => 'Apellido Incorrecto',
            'avatar_url' => 'https://cmsf.gob.ve/avatars/noe.png',
        ));
        $db->close();

        list($firstStatus, $firstOutput) = $this->runSeeder();
        self::assertSame(0, $firstStatus, $firstOutput);
        self::assertStringContainsString('Perfiles procesados: 23', $firstOutput);

        list($secondStatus, $secondOutput) = $this->runSeeder();
        self::assertSame(0, $secondStatus, $secondOutput);

        $db = new DB($this->databaseFile);
        try {
            self::assertSame(count($this->expectedProfiles), $db->countRows('profile'));
            foreach ($this->expectedProfiles as $username => $names) {
                $row = $db->fetchAllQuery(
                    'SELECT profile.first_name, profile.last_name, profile.avatar_url
                     FROM profile INNER JOIN users ON users.id = profile.user_id
                     WHERE users.username = :username',
                    array(':username' => $username)
                );
                self::assertCount(1, $row, 'Falta o sobra el perfil de ' . $username);
                self::assertSame($names[0], $row[0]['first_name']);
                self::assertSame($names[1], $row[0]['last_name']);
                self::assertSame(
                    $username === 'ndominguez' ? 'https://cmsf.gob.ve/avatars/noe.png' : null,
                    $row[0]['avatar_url']
                );
            }
        } finally {
            $db->close();
        }
    }

    public function testMissingUserRollsBackTheWholeSeed(): void
    {
        $db = new DB($this->databaseFile);
        $zrivas = $db->getFirstForConditions('users', array('username' => 'zrivas'));
        self::assertTrue($db->exec('DELETE FROM users WHERE id = ' . (int) $zrivas['id']));
        $db->close();

        list($status, $output) = $this->runSeeder();
        self::assertSame(1, $status, $output);
        self::assertStringContainsString('No existe el usuario personal: zrivas', $output);

        $db = new DB($this->databaseFile);
        try {
            self::assertSame(0, $db->countRows('profile'));
            self::assertSame(22, $db->countRows('users'));
        } finally {
            $db->close();
        }
    }

    private function runSeeder(): array
    {
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = array();
        }
        $environment['SYSTEC_DB'] = $this->databaseFile;

        $process = proc_open(
            array(PHP_BINARY, __DIR__ . '/../../bin/seed-profiles.php'),
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'),
            ),
            $pipes,
            null,
            $environment
        );
        self::assertIsResource($process, 'No se pudo iniciar el seeder de perfiles.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return array($exitCode, $stdout . $stderr);
    }
}
