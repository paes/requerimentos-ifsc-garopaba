-- Fix: normalizar nome de SCHIRLEI VON DENTZ para SCHIRLEI RUSSI VON DENTZ em 2026.1
-- O XML do aSc omite o sobrenome "RUSSI". teachers.name tem o nome completo.
-- Se o XML de 2026.1 for reimportado via schedule_uploads, este fix precisará ser reaplicado.

UPDATE schedule_slots
SET teacher_name = 'SCHIRLEI RUSSI VON DENTZ'
WHERE teacher_name = 'SCHIRLEI VON DENTZ'
  AND semester = '2026.1';

UPDATE teacher_availabilities
SET teacher_name = 'SCHIRLEI RUSSI VON DENTZ'
WHERE teacher_name = 'SCHIRLEI VON DENTZ'
  AND semester = '2026.1';
