ALTER TABLE superadmin_accounts
    ADD COLUMN full_name VARCHAR(150) NULL AFTER id,
    ADD COLUMN phone_number VARCHAR(20) NULL AFTER email;

ALTER TABLE superadmin_bootstrap_state
    ADD COLUMN otp_hash VARCHAR(255) NULL,
    ADD COLUMN otp_expires_at DATETIME NULL,
    ADD COLUMN otp_sent_at DATETIME NULL,
    ADD COLUMN otp_window_started_at DATETIME NULL,
    ADD COLUMN otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN otp_send_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN pending_full_name VARCHAR(150) NULL,
    ADD COLUMN pending_email VARCHAR(190) NULL,
    ADD COLUMN pending_phone_number VARCHAR(20) NULL,
    ADD COLUMN pending_password_hash VARCHAR(255) NULL;
