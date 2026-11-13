CREATE TABLE teachers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY teachers_school_email_unique (school_id, email),
    KEY teachers_school_id_index (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE teacher_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id INT NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY teacher_assignment_unique (teacher_id, class_id, subject_id),
    KEY teacher_assignments_school_id_index (school_id),
    KEY teacher_assignments_class_subject_index (class_id, subject_id),
    CONSTRAINT teacher_assignments_teacher_fk
        FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
