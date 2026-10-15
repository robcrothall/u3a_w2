-- 007_presentation_attendance.sql
-- Run once, after 001-006.
--
-- Number of people who attended a presentation in person. Entered by an admin
-- after the meeting; NULL means "not recorded" (so a missing figure is never
-- shown as 0).

ALTER TABLE presentations
    ADD COLUMN attendance SMALLINT UNSIGNED NULL;
