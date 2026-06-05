-- Sistema de coordenadores por semestre
-- Mantém histórico de quem estava em coordenação de curso/setor em cada semestre.

CREATE TABLE IF NOT EXISTS course_coordinators (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    semester     VARCHAR(10)  NOT NULL,
    teacher_id   INT          NULL,
    teacher_name VARCHAR(255) NOT NULL,
    course_id    INT          NULL,
    role_name    VARCHAR(255) NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)  ON DELETE SET NULL,
    FOREIGN KEY (course_id)  REFERENCES courses(id)   ON DELETE SET NULL,
    INDEX (semester),
    INDEX (semester, teacher_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
