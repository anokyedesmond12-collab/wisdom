ALTER TABLE students
    ADD COLUMN email_verified_at DATETIME NULL;

ALTER TABLE parents
    ADD COLUMN email_verified_at DATETIME NULL;

ALTER TABLE teachers
    ADD COLUMN account_status ENUM('pending', 'active') NOT NULL DEFAULT 'active';

CREATE TABLE portal_email_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role ENUM('student', 'parent') NOT NULL,
    email VARCHAR(190) NOT NULL,
    session_hash CHAR(64) NOT NULL,
    school_id INT DEFAULT NULL,
    student_admission VARCHAR(50) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    request_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    request_window_started_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    last_sent_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY portal_verification_role_email (role, email),
    KEY portal_verification_rate_limit (email, last_sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE superadmin_accounts (
    id TINYINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NOT NULL,
    last_email_verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY superadmin_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE superadmin_bootstrap_state (
    singleton_id TINYINT UNSIGNED NOT NULL,
    code_consumed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (singleton_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO superadmin_bootstrap_state (singleton_id, code_consumed_at) VALUES (1, NULL);
