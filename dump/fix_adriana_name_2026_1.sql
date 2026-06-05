-- Fix: normalizar nome de ADRIANA MURARA para ADRIANA MURARA SILVA em 2026.1
-- Motivo: schedule_slots e teacher_availabilities armazenam o nome vindo do XML do aSc
-- ("ADRIANA MURARA"), enquanto teachers.name e course_coordinators usam o nome completo
-- ("ADRIANA MURARA SILVA"). A divergência causa coordScore=0 e reclassificação incorreta
-- das restrições como voluntary em vez de institutional.
-- Se o XML de 2026.1 for reimportado via schedule_uploads, este fix precisará ser reaplicado.

UPDATE schedule_slots
SET teacher_name = 'ADRIANA MURARA SILVA'
WHERE teacher_name = 'ADRIANA MURARA'
  AND semester = '2026.1';

UPDATE teacher_availabilities
SET teacher_name = 'ADRIANA MURARA SILVA'
WHERE teacher_name = 'ADRIANA MURARA'
  AND semester = '2026.1';
