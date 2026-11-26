-- 011_rename_personal_usernames.sql
-- Corrige los usernames creados por 009 sin cambiar ids, perfiles ni credenciales.
-- Los emails institucionales se alinean con el nuevo username.

WITH renames(old_username, new_username) AS (
    VALUES
        ('arafael', 'abrizzuela'),
        ('amaria', 'amaurizio'),
        ('bmaria', 'brivero'),
        ('dalided', 'doropeza'),
        ('dmariela', 'dgarcia'),
        ('halejandra', 'hsanchez'),
        ('hnazareth', 'hperozo'),
        ('jcarlos', 'jguevara'),
        ('jalfredo', 'jpino'),
        ('lmanuel', 'lperaza'),
        ('lyetzabeth', 'loropeza'),
        ('mangelica', 'mrojas'),
        ('mauxiliadora', 'mtagliaferro'),
        ('mlourdes', 'mseguirit'),
        ('nmiguel', 'ndominguez'),
        ('oanival', 'orodriguez'),
        ('rrafael', 'rguevara'),
        ('rnacarith', 'rojeda'),
        ('ymaribel', 'yojeda'),
        ('ydel', 'ymarin'),
        ('ymarcela', 'yparadas'),
        ('zmercedes', 'znadal'),
        ('zjosefina', 'zrivas')
)
UPDATE users
SET username = (
        SELECT new_username FROM renames WHERE renames.old_username = users.username
    ),
    email = (
        SELECT new_username FROM renames WHERE renames.old_username = users.username
    ) || '@cmsf.gob.ve'
WHERE role = 'personal'
  AND username IN (SELECT old_username FROM renames);
