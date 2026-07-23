-- ============================================================
-- One-time repair script.
-- Run this ONCE in phpMyAdmin (SQL tab, on the smart_learning database)
-- to fix "3 of 1 completed"-style progress bars caused by videos or
-- lessons that were deleted BEFORE the admin delete endpoints were
-- updated to clean up after themselves.
--
-- Safe to run even if nothing is stale — it just recalculates the
-- real numbers from what currently exists.
-- ============================================================

USE smart_learning;

-- 1) Remove completion records that point at videos/lessons which no
--    longer exist (these are the orphans inflating the counts).
DELETE cc FROM content_completions cc
LEFT JOIN videos v ON cc.content_type = 'video' AND cc.content_id = v.id
WHERE cc.content_type = 'video' AND v.id IS NULL;

DELETE cc FROM content_completions cc
LEFT JOIN lessons l ON cc.content_type = 'lesson' AND cc.content_id = l.id
WHERE cc.content_type = 'lesson' AND l.id IS NULL;

-- 2) Reset every lessons_completed count to 0, then recompute it from
--    what's actually still left in content_completions, so the numbers
--    match reality exactly (rather than trust the old running totals).
UPDATE progress SET lessons_completed = 0;

UPDATE progress p
JOIN (
    SELECT student_id, subject_id, COUNT(*) AS cnt
    FROM (
        SELECT cc.student_id, v.subject_id
        FROM content_completions cc
        JOIN videos v ON cc.content_type = 'video' AND cc.content_id = v.id
        UNION ALL
        SELECT cc.student_id, l.subject_id
        FROM content_completions cc
        JOIN lessons l ON cc.content_type = 'lesson' AND cc.content_id = l.id
    ) combined
    GROUP BY student_id, subject_id
) agg ON agg.student_id = p.student_id AND agg.subject_id = p.subject_id
SET p.lessons_completed = agg.cnt;
