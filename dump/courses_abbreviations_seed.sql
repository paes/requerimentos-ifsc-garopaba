-- Popular abbreviations dos cursos existentes
UPDATE courses SET abbreviation = 'ADM'      WHERE id = 1;  -- Técnico em Administração
UPDATE courses SET abbreviation = 'LAZ'      WHERE id = 2;  -- Técnico em Lazer
UPDATE courses SET abbreviation = 'INF'      WHERE id = 3;  -- Técnico em Informática
UPDATE courses SET abbreviation = 'BTC'      WHERE id = 4;  -- Técnico em Biotecnologia
UPDATE courses SET abbreviation = 'SI'       WHERE id = 7;  -- CST em Sistemas para Internet
UPDATE courses SET abbreviation = 'GA'       WHERE id = 8;  -- CST em Gestão Ambiental
UPDATE courses SET abbreviation = 'FIC ING'  WHERE id = 15; -- FIC em Inglês
UPDATE courses SET abbreviation = 'GUIA'     WHERE id = 16; -- Técnico em Guia de Turismo
UPDATE courses SET abbreviation = 'PROEJA RB' WHERE id = 17; -- PROEJA em Serviços de Restauração e Bar

-- Criar curso PROEJA em Administração
INSERT INTO courses (name, abbreviation, active, is_ead)
VALUES ('PROEJA em Administração', 'PROEJA ADM', 1, 0);
