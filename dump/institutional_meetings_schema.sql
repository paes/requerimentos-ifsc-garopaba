-- Quando cada papel institucional se reúne, por semestre
-- (configuração muda entre semestres, ex: coordenadores mudaram de dia em 2026.2)
CREATE TABLE IF NOT EXISTS institutional_meetings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    semester    VARCHAR(10)  NOT NULL,
    role_type   ENUM('coordinator','colegiado','other') NOT NULL,
    day_of_week TINYINT      NOT NULL,   -- 1=Seg … 5=Sex
    time_slot   VARCHAR(20)  NOT NULL,
    notes       VARCHAR(255) NULL,
    INDEX idx_semester      (semester),
    INDEX idx_semester_role (semester, role_type)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
