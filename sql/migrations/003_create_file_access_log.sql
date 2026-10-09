-- 003_create_file_access_log.sql
-- Records which member opened which recording/document link (for the
-- committee's usage statistics). Deliberately stores only user + file + time:
-- no IP address or browser details (POPIA - data minimisation).

CREATE TABLE IF NOT EXISTS file_access_log (
    id        BIGINT AUTO_INCREMENT PRIMARY KEY,
    file_id   INT NULL,
    user_id   INT NULL,
    accessed  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_access_log_file FOREIGN KEY (file_id) REFERENCES presentation_files(id) ON DELETE SET NULL,
    CONSTRAINT fk_access_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_access_log_file (file_id),
    INDEX idx_access_log_accessed (accessed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
