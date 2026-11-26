-- 009_seed_personal_users.sql
-- Usernames canónicos indicados para el personal, en minusculas.
-- Se omiten las personas marcadas como Renuncia y la ficha Sin ficha.
-- Las cuentas existentes se conservan sin modificar.
-- Zuleima se crea inactiva porque la ficha indica Inactivo.

ALTER TABLE users ADD COLUMN email TEXT;

-- Cada seed usa un hash bcrypt independiente para la clave inicial cmsf.
INSERT OR IGNORE INTO users (username, password_hash, role, active, email)
VALUES
    ('abrizzuela', '$2y$10$jX6QkL.H473hH6hSZykaSe0cCq8ZWD9Z7hsgcyJ1S2ynRWkR56/G2', 'personal', 1, 'abrizzuela@cmsf.gob.ve'),
    ('amaurizio', '$2y$10$mof.5DZLXCmb4V9xs.ksXO//DLfRGBEYAZbGu.nCc.N.tZO8xRWNW', 'personal', 1, 'amaurizio@cmsf.gob.ve'),
    ('brivero', '$2y$10$Zl0BrTV/x2QhD7/BPKmo5O0Tz6MZ1cM9Owr.dHYAa5e7XcnfQ6V.u', 'personal', 1, 'brivero@cmsf.gob.ve'),
    ('doropeza', '$2y$10$IbsI7SRdWjJp85oxcLh6NeMUEZa8OGOfnNQGeZ9TfiKE9SaAGlzO6', 'personal', 1, 'doropeza@cmsf.gob.ve'),
    ('dgarcia', '$2y$10$bP/RoES63HREVqWG7gU9MOmq86LalY2E4AdWMpUrgnbkMS4cDjrve', 'personal', 1, 'dgarcia@cmsf.gob.ve'),
    ('hsanchez', '$2y$10$b4Xe5O2niEQexLoys35Yje1FXBT1piV7yDjymcSMecDoDUt52tG7S', 'personal', 1, 'hsanchez@cmsf.gob.ve'),
    ('hperozo', '$2y$10$UJYN9FhgXnlUn0vOJ1p.Uef2Oz4PcJ87kHmeAU.YMRZ2rvJ.XL4Ia', 'personal', 1, 'hperozo@cmsf.gob.ve'),
    ('jguevara', '$2y$10$Zo6XF9nA3oBo8fPwBOigc.hFTNQQnNk8GAwMZ9jIKL08sSeAH8pmG', 'personal', 1, 'jguevara@cmsf.gob.ve'),
    ('jpino', '$2y$10$XCM4gTGX1RvpgcGDKZg8bu09pZkdZG4svMhvXQSRifsdeB92sIcsG', 'personal', 1, 'jpino@cmsf.gob.ve'),
    ('lperaza', '$2y$10$0BWuIj7BRFx5FZDpvSd8Eez50ba3X4KiqDmcmn3YDIUXmLytEfXw.', 'personal', 1, 'lperaza@cmsf.gob.ve'),
    ('loropeza', '$2y$10$nd3xtB3ezv9W8e4xOUmfqu7ujTDTuHeqClFFJTn0L3c/n6w7zh9LC', 'personal', 1, 'loropeza@cmsf.gob.ve'),
    ('mrojas', '$2y$10$kzOhCJqppjDkATlYEHthj.lbrY9J02/JNIzDJHQGySyd.POTmS8DO', 'personal', 1, 'mrojas@cmsf.gob.ve'),
    ('mtagliaferro', '$2y$10$cTXUNIk9okOdIaVoMHu7YuK4RWkezScihsyjn0LVbEdYe1wYOhBn2', 'personal', 1, 'mtagliaferro@cmsf.gob.ve'),
    ('mseguirit', '$2y$10$khY9SIhys32jWRq/6fN95usQQNKl6N1aVbskNiIjzYBBJqu0ziwRq', 'personal', 1, 'mseguirit@cmsf.gob.ve'),
    ('ndominguez', '$2y$10$yXpYNJ11FPe8eRcIMA.v9uOtAp79L5.HyzQQ4kqHvQHVp/E4tmkHm', 'personal', 1, 'ndominguez@cmsf.gob.ve'),
    ('orodriguez', '$2y$10$7QmcWOEa7I5swfCCqtbJ9.godt8EiyWLGVSxABGQtAmskUGn7gpOq', 'personal', 1, 'orodriguez@cmsf.gob.ve'),
    ('rguevara', '$2y$10$ab9hptjN1GZmcdsM4y72WOEphn05fpK50J.Zr7BkvTt1Dobqg4L0e', 'personal', 1, 'rguevara@cmsf.gob.ve'),
    ('rojeda', '$2y$10$wwNJSnZ9x3BS04Z/B1eQ1OABXmzMTmcL0j/SIbHtB/lQH/NXslAu6', 'personal', 1, 'rojeda@cmsf.gob.ve'),
    ('yojeda', '$2y$10$uIhVxp/nvy.vHs8hmATkRupRnYMtjZUJ/6Vdji/ACL8VJL7bmWjHm', 'personal', 1, 'yojeda@cmsf.gob.ve'),
    ('ymarin', '$2y$10$rdwNKzz/9nlSx0DdmK.uauhMhay5WOWIt/qfugLO61yjI.3s2Tij.', 'personal', 1, 'ymarin@cmsf.gob.ve'),
    ('yparadas', '$2y$10$i42ya291R9FZtkWICGhwselUf6XD/ZnVKgGrI.rCBPdqKFZaMT6oa', 'personal', 1, 'yparadas@cmsf.gob.ve'),
    ('znadal', '$2y$10$2ymusqA1CohT2xp0NWnxGu5QSBJ5rP7AjR2W.fPtgcUWz0yuQZi9i', 'personal', 1, 'znadal@cmsf.gob.ve'),
    ('zrivas', '$2y$10$iUxbBFRUg/ad3RaH1F.i6.ff8C5syBUn1n3kNZqEDnAL74k77CxzG', 'personal', 0, 'zrivas@cmsf.gob.ve');

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique
    ON users (email)
    WHERE email IS NOT NULL AND trim(email) <> '';
