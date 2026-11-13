ALTER TABLE superadmin_accounts
    ADD COLUMN login_otp_hash VARCHAR(255) NULL,
    ADD COLUMN login_otp_expires_at DATETIME NULL,
    ADD COLUMN login_otp_sent_at DATETIME NULL,
    ADD COLUMN login_otp_window_started_at DATETIME NULL,
    ADD COLUMN login_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN login_otp_send_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN login_otp_session_hash CHAR(64) NULL;
