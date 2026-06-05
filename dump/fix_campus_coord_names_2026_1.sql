-- Corrigir nomes abreviados salvos pelo DnD para nomes canônicos completos
UPDATE course_coordinators SET teacher_name = 'ROSANE SCHENKEL DE AQUINO',  teacher_id = 45 WHERE semester = '2026.1' AND teacher_name = 'Rosane Aquino';
UPDATE course_coordinators SET teacher_name = 'JOÃO EDUARDO NAVACHI DA SILVEIRA', teacher_id = 22 WHERE semester = '2026.1' AND teacher_name = 'João Silveira';
UPDATE course_coordinators SET teacher_name = 'RENATA WALESKA DE SOUSA PIMENTA', teacher_id = 43 WHERE semester = '2026.1' AND teacher_name = 'Renata Pimenta';
UPDATE course_coordinators SET teacher_name = 'NAUBER GAVSKI DA SILVA',       teacher_id = 41 WHERE semester = '2026.1' AND teacher_name = 'Nauber Silva';
UPDATE course_coordinators SET teacher_name = 'FABIANA DE AGAPITO KANGERSKI', teacher_id = 15 WHERE semester = '2026.1' AND teacher_name = 'Fabiana Kangerski';
UPDATE course_coordinators SET teacher_name = 'JEAN MARCEL DE ALMEIDA ESPINOZA', teacher_id = 21 WHERE semester = '2026.1' AND teacher_name = 'Jean Espinoza';
