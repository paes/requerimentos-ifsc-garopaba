CREATE TABLE IF NOT EXISTS teacher_availabilities (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    semester         VARCHAR(10)  NOT NULL,
    teacher_name     VARCHAR(255) NOT NULL,
    day_of_week      TINYINT      NOT NULL,  -- 1=Seg … 5=Sex
    time_slot        VARCHAR(20)  NOT NULL,
    is_available     TINYINT(1)   NOT NULL,
    restriction_type ENUM('institutional','voluntary') NULL DEFAULT 'voluntary',
    notes            VARCHAR(500) NULL,
    UNIQUE KEY uniq_slot (semester, teacher_name, day_of_week, time_slot),
    INDEX idx_semester         (semester),
    INDEX idx_semester_teacher (semester, teacher_name)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
