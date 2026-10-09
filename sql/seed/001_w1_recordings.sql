-- seed/001_w1_recordings.sql  (OPTIONAL - run once, after migrations 001-003)
-- Copies the two presentations and their links from the old w1 recordings
-- page into the new tables. Safe to run twice: rows are only added if missing.

-- 2026-07-09 Gillian McGregor
INSERT INTO presentations (presenter_name, title, presentation_date, status)
SELECT 'Gillian McGregor', 'Aspects of the Sustainability of the Wild Honeybush Industry', '2026-07-09', 'published'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM presentations WHERE presentation_date = '2026-07-09' AND presenter_name = 'Gillian McGregor');

INSERT INTO presentation_files (presentation_id, url, link_text, file_type, sort_order)
SELECT p.id, 'https://drive.google.com/file/d/1NKnsDgJ6KGssan9fMsaqYdrCMc3iihEG/view?usp=sharing', 'Video recording', 'video', 1
FROM presentations p
WHERE p.presentation_date = '2026-07-09' AND p.presenter_name = 'Gillian McGregor'
AND NOT EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id AND f.sort_order = 1);

-- 2026-06-25 Harold Hopkins
INSERT INTO presentations (presenter_name, title, presentation_date, status)
SELECT 'Harold Hopkins', 'China', '2026-06-25', 'published'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM presentations WHERE presentation_date = '2026-06-25' AND presenter_name = 'Harold Hopkins');

INSERT INTO presentation_files (presentation_id, url, link_text, file_type, sort_order)
SELECT p.id, 'https://drive.google.com/file/d/1IPl4MJd0hwYSRz17_VxaksgMZ4fJ8GWQ/view?usp=sharing', 'Video recording - Part 1', 'video', 1
FROM presentations p
WHERE p.presentation_date = '2026-06-25' AND p.presenter_name = 'Harold Hopkins'
AND NOT EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id AND f.sort_order = 1);

INSERT INTO presentation_files (presentation_id, url, link_text, file_type, sort_order)
SELECT p.id, 'https://drive.google.com/file/d/1c9uBbCGm8wBvJHmTQBFYb33BUfppgFK9/view?usp=sharing', 'Video recording - Part 2', 'video', 2
FROM presentations p
WHERE p.presentation_date = '2026-06-25' AND p.presenter_name = 'Harold Hopkins'
AND NOT EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id AND f.sort_order = 2);

INSERT INTO presentation_files (presentation_id, url, link_text, file_type, sort_order)
SELECT p.id, 'https://drive.google.com/file/d/1kL-ksqEbU1nNuTcpohtMwrWVgPpUnhtB/view?usp=sharing', 'Report on the talk by The Announcer', 'article', 3
FROM presentations p
WHERE p.presentation_date = '2026-06-25' AND p.presenter_name = 'Harold Hopkins'
AND NOT EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id AND f.sort_order = 3);

INSERT INTO presentation_files (presentation_id, url, link_text, file_type, sort_order)
SELECT p.id, 'https://docs.google.com/presentation/d/1Z8zGuP4KFM6a5r0PSoaETnTeZYvgw1M2/edit?usp=sharing', 'PowerPoint deck', 'presentation', 4
FROM presentations p
WHERE p.presentation_date = '2026-06-25' AND p.presenter_name = 'Harold Hopkins'
AND NOT EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id AND f.sort_order = 4);
