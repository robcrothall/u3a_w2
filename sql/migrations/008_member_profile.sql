-- 008_member_profile.sql
-- Run once, after 001-007.
--
-- Extra member information requested by the Committee:
--   title            free text, may hold more than one title (e.g. "Rev Dr")
--   mobile           stored as "083 678 5050" (South Africa) or "(+44) 770 090 0123"
--   can_present      "Are you prepared to present? If so, in what field?"   (max 250)
--   skills           "What are your skills or expertise?"                   (max 250)
--   wants_subjects   "What subjects or information would you like offered?" (max 250)
--   consent_version / consent_at   which version of the participation and
--                    privacy acknowledgement the member accepted, and when
--
-- Then: imported phone numbers that are mobiles (06x/07x/08x) are moved from
-- "phone" into "mobile". "phone" keeps landlines and anything else.

ALTER TABLE users
    ADD COLUMN title           VARCHAR(40)  NULL,
    ADD COLUMN mobile          VARCHAR(30)  NULL,
    ADD COLUMN can_present     VARCHAR(250) NULL,
    ADD COLUMN skills          VARCHAR(250) NULL,
    ADD COLUMN wants_subjects  VARCHAR(250) NULL,
    ADD COLUMN consent_version SMALLINT     NULL,
    ADD COLUMN consent_at      DATETIME     NULL;

-- "083 111 2222; 046 222 3333"  ->  mobile "083 111 2222", phone "046 222 3333"
UPDATE users
SET mobile = SUBSTRING_INDEX(phone, '; ', 1),
    phone  = NULLIF(TRIM(SUBSTRING(phone, LOCATE('; ', phone) + 2)), '')
WHERE mobile IS NULL AND phone REGEXP '^0[678][0-9] [0-9]{3} [0-9]{4}; ';

-- "083 111 2222"  ->  mobile, phone empty
UPDATE users
SET mobile = phone, phone = NULL
WHERE mobile IS NULL AND phone REGEXP '^0[678][0-9] [0-9]{3} [0-9]{4}$';
