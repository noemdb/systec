-- 010_create_profile.sql
-- Perfil uno a uno por usuario. Se usa el primer token como nombre y los
-- dos últimos como apellidos; los tokens intermedios no se guardan,
-- siguiendo el ejemplo Noe Miguel Dominguez Brito -> Noe / Dominguez Brito.

CREATE TABLE IF NOT EXISTS profile (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    first_name TEXT NOT NULL,
    last_name  TEXT NOT NULL,
    avatar_url TEXT
);

WITH seed(username, first_name, last_name) AS (
    VALUES
        ('abrizzuela', 'Alberto', 'Brizuela Ochoa'),
        ('amaurizio', 'Angela', 'Maurizio Perez'),
        ('brivero', 'Belkis', 'Rivero Miranda'),
        ('doropeza', 'Daxy', 'Oropeza Asuaje'),
        ('dgarcia', 'Dilcia', 'Garcia Gonzalez'),
        ('hsanchez', 'Helen', 'Sanchez Mauricio'),
        ('hperozo', 'Hillary', 'Perozo Montes'),
        ('jguevara', 'Juan', 'Guevara Sanchez'),
        ('jpino', 'Julio', 'Pino Escarra'),
        ('lperaza', 'Leonardo', 'Peraza Castillo'),
        ('loropeza', 'Lizmareth', 'Oropeza Asuaje'),
        ('mrojas', 'Maria', 'Rojas Parra'),
        ('mtagliaferro', 'Maria', 'Tagliaferro Aldama'),
        ('mseguirit', 'Mirian', 'Seguerit Alcalde'),
        ('ndominguez', 'Noe', 'Dominguez Brito'),
        ('orodriguez', 'Osvaldo', 'Rodriguez'),
        ('rguevara', 'Rene', 'Guevara Alejos'),
        ('rojeda', 'Reysi', 'Ojeda Torres'),
        ('yojeda', 'Yajaira', 'Ojeda Aviles'),
        ('ymarin', 'Yamileth', 'Marin Castro'),
        ('yparadas', 'Yarait', 'Paradas Castillo'),
        ('znadal', 'Zenaida', 'Nadal Vargas'),
        ('zrivas', 'Zuleima', 'Rivas Vasquez')
)
INSERT OR IGNORE INTO profile (user_id, first_name, last_name, avatar_url)
SELECT users.id, seed.first_name, seed.last_name, NULL
FROM seed
INNER JOIN users ON users.username = seed.username;
