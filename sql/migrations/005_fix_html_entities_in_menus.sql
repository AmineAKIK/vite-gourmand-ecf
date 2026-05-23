-- ============================================
-- Fix : décoder les entités HTML stockées par erreur dans menu.description et menu.conditions
-- Cause : menuPayloadFromRequest() appelait sanitize() (htmlspecialchars) avant INSERT/UPDATE
-- ============================================

UPDATE menu SET
    description = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(description,
        '&amp;',  '&'),
        '&#039;', ''''),
        '&quot;', '"'),
        '&lt;',   '<'),
        '&gt;',   '>'),
    conditions = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(conditions,
        '&amp;',  '&'),
        '&#039;', ''''),
        '&quot;', '"'),
        '&lt;',   '<'),
        '&gt;',   '>')
WHERE description LIKE '%&amp;%'
   OR description LIKE '%&#039;%'
   OR description LIKE '%&quot;%'
   OR conditions  LIKE '%&amp;%'
   OR conditions  LIKE '%&#039;%'
   OR conditions  LIKE '%&quot;%';
