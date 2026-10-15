-- 010_payment_receipts.sql
-- Run once, after 001-009.
--
-- Remembers when a receipt for a membership payment was emailed, so staff can
-- see which payments have been acknowledged and can resend one if needed.

ALTER TABLE membership_payments
    ADD COLUMN receipt_sent_at DATETIME NULL;
