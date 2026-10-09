-- 004_memberships_and_member_details.sql
-- Run once, after 001-003.
--
-- A "membership" is the unit that pays and is paid-up. It has 1 person
-- (individual), 2 people (couple) or is honorary (paid-up for life; the
-- person and their partner). Every user may belong to one membership:
--   * individual  R50 a year, one person
--   * couple      R80 a year, two people; when it is paid BOTH are paid-up
--   * honorary    no payment needed, paid-up for life
-- users.membership_id NULL is treated as an individual membership.
--
-- Also adds contact details to users, and allows a user without an email
-- address (e.g. the second person of a couple who share one address).
-- A user without an email cannot log in until one is added.

CREATE TABLE IF NOT EXISTS memberships (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    membership_type  ENUM('individual','couple','honorary') NOT NULL DEFAULT 'individual',
    created          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    MODIFY email VARCHAR(255) NULL,
    ADD COLUMN email2        VARCHAR(255) NULL AFTER email,
    ADD COLUMN phone         VARCHAR(100) NULL,
    ADD COLUMN address       VARCHAR(500) NULL,
    ADD COLUMN membership_id INT NULL,
    ADD INDEX idx_users_membership (membership_id),
    ADD CONSTRAINT fk_users_membership
        FOREIGN KEY (membership_id) REFERENCES memberships(id) ON DELETE SET NULL;
