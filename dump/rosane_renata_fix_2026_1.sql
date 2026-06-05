-- Consolidar Rosane: ROSANE e AQUINO eram a mesma pessoa (importação duplicada)
UPDATE teacher_availabilities
SET teacher_name = 'ROSANE SCHENKEL DE AQUINO'
WHERE teacher_name = 'ROSANE';

DELETE FROM teacher_availabilities
WHERE teacher_name = 'AQUINO';

-- Corrigir schedule_slots: "ROSANE, AQUINO" → nome completo
UPDATE schedule_slots
SET teacher_name = 'ROSANE SCHENKEL DE AQUINO',
    teacher_id   = 45
WHERE teacher_name = 'ROSANE, AQUINO';

-- Corrigir grafia do nome da Renata em teacher_availabilities (SOUZA → SOUSA)
UPDATE teacher_availabilities
SET teacher_name = 'RENATA WALESKA DE SOUSA PIMENTA'
WHERE teacher_name = 'RENATA WALESKA DE SOUZA PIMENTA';

-- Cadastrar Renata como Diretora do Câmpus no semestre atual
INSERT INTO course_coordinators (semester, teacher_id, teacher_name, course_id, role_name)
VALUES ('2026.1', 43, 'RENATA WALESKA DE SOUSA PIMENTA', NULL, 'Direção do Câmpus');
