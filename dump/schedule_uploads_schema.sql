-- Tabela para armazenar os PDFs de horários semestrais (docentes e turmas)
-- Fase 2 — Portal de Substituição de Aulas
-- Executar após teacher_substitution_schema.sql

CREATE TABLE IF NOT EXISTS schedule_uploads (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    type          ENUM('teachers','classes') NOT NULL COMMENT 'teachers=horários de docentes; classes=horários de turmas',
    semester      VARCHAR(10)  NOT NULL COMMENT 'ex: 2026.1, 2025.2',
    filename      VARCHAR(255) NOT NULL COMMENT 'nome do arquivo em storage/schedules/',
    original_name VARCHAR(255) NOT NULL COMMENT 'nome original do upload',
    uploaded_by   INT          NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
