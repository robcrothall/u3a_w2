-- 001_create_presentations.sql
-- Presentations (talks) and their linked recordings/documents.
-- These two tables also back the homepage's Upcoming/Past events -
-- "upcoming" and "past" are just queries against presentation_date,
-- not separate tables.

CREATE TABLE IF NOT EXISTS presentations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    presenter_name    VARCHAR(255) NOT NULL,
    title             VARCHAR(255) NOT NULL,
    presentation_date DATE NOT NULL,
    summary           TEXT NULL,
    status            ENUM('draft','published') NOT NULL DEFAULT 'published',
    created           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presentation_files (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    presentation_id  INT NOT NULL,
    url              VARCHAR(500) NOT NULL,
    link_text        VARCHAR(255) NOT NULL,
    file_type        ENUM('video','document','presentation','article','other') NOT NULL DEFAULT 'video',
    sort_order       INT NOT NULL DEFAULT 0,
    created          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_presentation_files_presentation
        FOREIGN KEY (presentation_id) REFERENCES presentations(id) ON DELETE CASCADE,
    INDEX idx_presentation_files_presentation (presentation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;