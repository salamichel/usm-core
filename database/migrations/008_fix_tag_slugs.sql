-- Régénérer les slugs vides pour les tags
-- Cette migration corrige les tags créés avant la fix du slug auto-generation
UPDATE tags
SET slug = LOWER(
  CONCAT(
    REPLACE(
      REPLACE(
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
          REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            name,
            'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'),
          'à', 'a'), 'â', 'a'), 'ä', 'a'),
          'ù', 'u'), 'û', 'u'), 'ü', 'u'),
          'ç', 'c'), 'ñ', 'n'),
          'ô', 'o'), 'ö', 'o'), 'ó', 'o'),
        ' ', '-'),
      '--', '-')
    )
  )
)
WHERE slug IS NULL OR slug = '';
