-- Portal de Substituição de Aulas — Fase 1
-- Executar uma vez no banco ifsc_requests

-- Novo role para Assessoria do Departamento de Ensino
INSERT INTO roles (name, is_course_bound, is_sysadmin) VALUES ('Assessoria DEPE', 0, 0);

-- Novo role para docentes (acesso ao portal /docentes/)
INSERT INTO roles (name, is_course_bound, is_sysadmin) VALUES ('Docente', 0, 0);

-- Requerimentos de substituição enviados por docentes
CREATE TABLE IF NOT EXISTS teacher_requests (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    protocol_code        VARCHAR(20)  NOT NULL UNIQUE,
    teacher_id           INT          DEFAULT NULL,
    teacher_name         VARCHAR(255) NOT NULL,
    teacher_email        VARCHAR(255) NOT NULL,
    course_id            INT          NOT NULL,
    class_group          VARCHAR(100) DEFAULT NULL,
    subject_name         VARCHAR(255) DEFAULT NULL,
    absence_dates        JSON         NOT NULL,
    periods              JSON         DEFAULT NULL,
    reason               TEXT         DEFAULT NULL,
    suggested_teacher_id INT          DEFAULT NULL,
    devolutiva_agreed    TINYINT(1)   NOT NULL DEFAULT 0,
    status               ENUM('pending','approved','rejected','concluded') NOT NULL DEFAULT 'pending',
    current_step_order   INT          NOT NULL DEFAULT 1,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Histórico de ações sobre requerimentos de docentes
CREATE TABLE IF NOT EXISTS teacher_request_history (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    teacher_request_id  INT NOT NULL,
    user_id             INT NOT NULL,
    action              ENUM('approve','reject','comment') NOT NULL,
    observation         TEXT DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_request_id) REFERENCES teacher_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
