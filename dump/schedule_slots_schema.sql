-- Fase 3: Grade de Horários + Sugestão de Substitutos
-- Executar após schedule_uploads_schema.sql

CREATE TABLE IF NOT EXISTS schedule_slots (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    semester     VARCHAR(10)  NOT NULL,
    teacher_id   INT          NULL,
    teacher_name VARCHAR(255) NOT NULL,
    class_group  VARCHAR(100) NOT NULL,
    subject_name VARCHAR(255),
    day_of_week  TINYINT      NOT NULL COMMENT '1=Seg … 5=Sex',
    time_slot    VARCHAR(20)  NOT NULL COMMENT 'manha12|manha34|tarde12|tarde34|noite12|noite34|noite123',
    room         VARCHAR(150),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Renomear coluna periods → time_slots em teacher_requests
-- (armazena códigos de turno como ["manha12","tarde34"] em vez de números [1,3])
ALTER TABLE teacher_requests CHANGE COLUMN periods time_slots JSON;

-- Fase 3.1: Suporte a durações (terms) do aSc Timetables
-- terms = bitmask 4 bits: "1111"=semestre completo, "1100"=D1+D2 (sem. 1-10), etc.
ALTER TABLE schedule_slots
    ADD COLUMN terms VARCHAR(10) NOT NULL DEFAULT '1111'
    AFTER time_slot;
