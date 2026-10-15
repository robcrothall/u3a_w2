-- 011_bulk_email.sql
-- Run once, after 001-010.
--
-- Bulk email to members:
--   mail_campaigns  one row per message an admin composes (subject, body with
--                   merge keywords, audience, optional Resources documents).
--   mail_queue      one row per recipient; messages are sent in batches, each
--                   personalised at send time. This is also the sent log.
--   users.unsubscribe_token  secret used in the one-click unsubscribe link
--                   included in every message; created when first needed.

CREATE TABLE IF NOT EXISTS mail_campaigns (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    subject     VARCHAR(150) NOT NULL,
    body        TEXT NOT NULL,
    audience    ENUM('all','unpaid','admins') NOT NULL,
    doc_ids     VARCHAR(60) NULL,
    attach      TINYINT(1) NOT NULL DEFAULT 0,
    status      ENUM('queued','done','cancelled') NOT NULL DEFAULT 'queued',
    recipients  INT NOT NULL DEFAULT 0,
    created     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by  INT NULL,
    CONSTRAINT fk_mail_campaigns_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_queue (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id  INT NOT NULL,
    user_id      INT NULL,
    email        VARCHAR(255) NOT NULL,
    status       ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    sent_at      DATETIME NULL,
    note         VARCHAR(200) NULL,
    CONSTRAINT fk_mail_queue_campaign FOREIGN KEY (campaign_id) REFERENCES mail_campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_mail_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_mail_queue_campaign (campaign_id, status),
    INDEX idx_mail_queue_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    ADD COLUMN unsubscribe_token CHAR(32) NULL,
    ADD UNIQUE KEY uq_users_unsubscribe_token (unsubscribe_token);
