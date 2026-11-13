CREATE TABLE teacher_attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id INT NOT NULL,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    teacher_id INT UNSIGNED DEFAULT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY teacher_attendance_daily_student_unique (school_id, class_id, student_id, attendance_date),
    KEY teacher_attendance_class_date_index (school_id, class_id, attendance_date),
    KEY teacher_attendance_student_date_index (school_id, student_id, attendance_date),
    KEY teacher_attendance_teacher_index (teacher_id),
    CONSTRAINT teacher_attendance_teacher_fk
        FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
