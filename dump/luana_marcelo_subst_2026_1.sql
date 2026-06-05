-- Substituição docente 2026.1: Luana De Gusmão Silveira → Marcelo Leandro dos Santos
-- Motivo: LUANA não lecionou no semestre 2026.1; MARCELO (id=35) assumiu todas as turmas
UPDATE schedule_slots
SET teacher_name = 'MARCELO LEANDRO DOS SANTOS',
    teacher_id   = 35
WHERE semester     = '2026.1'
  AND teacher_name = 'LUANA DE GUSMÃO SILVEIRA';
