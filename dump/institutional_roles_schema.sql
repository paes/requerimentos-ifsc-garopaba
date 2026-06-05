-- Quem possui papel institucional por semestre (além de coordenadores, que ficam em course_coordinators)
CREATE TABLE IF NOT EXISTS institutional_roles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    semester     VARCHAR(10)  NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    role_type    ENUM('colegiado','other') NOT NULL,
    notes        VARCHAR(255) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role (semester, teacher_name, role_type),
    INDEX idx_semester (semester)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
