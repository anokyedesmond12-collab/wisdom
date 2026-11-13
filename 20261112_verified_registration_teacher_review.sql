ALTER TABLE teachers
    MODIFY account_status ENUM('pending', 'active', 'declined') NOT NULL DEFAULT 'active';
