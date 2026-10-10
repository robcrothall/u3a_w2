-- 005_password_resets_and_site_content.sql
-- Run once, after 001-004.
--
-- password_resets: one-time "set/forgot your password" links sent by email.
--   Only a SHA-256 hash of the token is stored, so a copy of the database
--   cannot be used to take over an account.
-- site_content: small editable pieces of the public site (the About U3A text
--   and the committee photo's file name), changed by staff.

CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    purpose     ENUM('reset','invite') NOT NULL DEFAULT 'reset',
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_password_resets_token (token_hash),
    INDEX idx_password_resets_user (user_id, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_content (
    content_key    VARCHAR(50) NOT NULL PRIMARY KEY,
    content_value  TEXT NULL,
    updated        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT NULL,
    CONSTRAINT fk_site_content_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
