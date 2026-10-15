-- 009_resources.sql
-- Run once, after 001-008.
--
-- "Resources": interesting links and documents (newsletters, speaker
-- instructions, bursary information ...), one list, newest first.
--   kind        'link' (url) or 'document' (a file kept in the private storage
--               folder, served through resource.php after a permission check)
--   title       the text shown as the link, up to 80 characters
--   information a short description shown under the title
--   visibility  everyone = anyone visiting the site
--               members  = logged-in registered users
--               admins   = logged-in admins only
--   stored_name the random file name on disk; file_name is the name offered on download

CREATE TABLE IF NOT EXISTS resources (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    kind         ENUM('link','document') NOT NULL,
    title        VARCHAR(80)  NOT NULL,
    information  VARCHAR(1000) NULL,
    category     VARCHAR(40)  NULL,
    visibility   ENUM('everyone','members','admins') NOT NULL DEFAULT 'everyone',
    date_added   DATE NOT NULL,
    url          VARCHAR(500) NULL,
    file_name    VARCHAR(255) NULL,
    stored_name  VARCHAR(80)  NULL,
    file_size    INT UNSIGNED NULL,
    created      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by   INT NULL,
    CONSTRAINT fk_resources_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_resources_visibility_date (visibility, date_added)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
