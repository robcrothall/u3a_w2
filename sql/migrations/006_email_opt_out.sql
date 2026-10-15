-- 006_email_opt_out.sql
-- Run once, after 001-005.
--
-- "Do not send newsletters or announcements": a member who has asked not to be
-- emailed keeps their record (membership and payment history are still needed),
-- but is left out of mailing lists, mail-merge exports and bulk invitations.
-- Service messages the person asks for themselves (password links) and
-- payment receipts are NOT affected.
--
-- email_opt_out_at records when the choice was made (POPIA: keep a record of
-- an objection so it can be honoured).

ALTER TABLE users
    ADD COLUMN email_opt_out    TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN email_opt_out_at DATETIME NULL;
