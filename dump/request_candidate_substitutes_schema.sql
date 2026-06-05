-- Candidatos a substituto para cada solicitação de substituição de docente.
-- Cada candidato recebe um token único para aceitar/recusar via link de e-mail.

CREATE TABLE IF NOT EXISTS request_candidate_substitutes (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    teacher_request_id INT NOT NULL,
    teacher_name       VARCHAR(255) NOT NULL,
    teacher_email      VARCHAR(255) NOT NULL,
    status             ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
    token              VARCHAR(64)  NOT NULL UNIQUE,
    responded_at       DATETIME     NULL,
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_request_id) REFERENCES teacher_requests(id) ON DELETE CASCADE,
    INDEX (token),
    INDEX (teacher_request_id, status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
