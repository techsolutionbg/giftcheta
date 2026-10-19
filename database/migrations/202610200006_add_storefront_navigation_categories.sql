INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
VALUES
  (NULL, 'Персонализирани подаръци', 'personalizirani-podaratsi', 'Персонални подаръци със снимка, послание или гравиране.', 1, 8, 'Персонализирани подаръци', 'Изберете персонален подарък със специално послание.'),
  (NULL, 'За нея', 'podaratsi-za-neya', 'Подбрани подаръци за нея.', 1, 9, 'Подаръци за нея', 'Открийте подаръци за любимата жена.'),
  (NULL, 'За него', 'podaratsi-za-nego', 'Подбрани подаръци за него.', 1, 10, 'Подаръци за него', 'Открийте подаръци за любимия мъж.')
ON DUPLICATE KEY UPDATE
  parent_id = NULL,
  name = VALUES(name),
  description = VALUES(description),
  is_active = 1,
  sort_order = VALUES(sort_order),
  seo_title = VALUES(seo_title),
  seo_description = VALUES(seo_description),
  deleted_at = NULL;

INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
SELECT parent.id, child.name, child.slug, child.description, 1, child.sort_order, child.seo_title, child.seo_description
FROM (
  SELECT 'personalizirani-podaratsi' AS parent_slug, 'Фото подаръци' AS name, 'foto-podaraci' AS slug, 'Подаръци с любими снимки и спомени.' AS description, 1 AS sort_order, 'Фото подаръци' AS seo_title, 'Персонализирани фото подаръци със снимки.' AS seo_description
  UNION ALL SELECT 'personalizirani-podaratsi', 'Гравирани подаръци', 'gravirani-podaratsi', 'Подаръци с персонално гравирано послание.', 2, 'Гравирани подаръци', 'Персонални подаръци с гравиране.'
  UNION ALL SELECT 'podaratsi-za-neya', 'Бижута за нея', 'bijuta-za-neya', 'Изящни подаръци и бижута за нея.', 1, 'Бижута за нея', 'Бижута и подаръци за нея.'
  UNION ALL SELECT 'podaratsi-za-neya', 'Аксесоари за нея', 'aksesoari-za-neya', 'Стилни аксесоари за специални поводи.', 2, 'Аксесоари за нея', 'Аксесоари и идеи за подарък за нея.'
  UNION ALL SELECT 'podaratsi-za-nego', 'Аксесоари за него', 'aksesoari-za-nego', 'Практични и стилни аксесоари за него.', 1, 'Аксесоари за него', 'Аксесоари и идеи за подарък за него.'
  UNION ALL SELECT 'podaratsi-za-nego', 'Гаджети за него', 'gadjeti-za-nego', 'Интересни технологични подаръци и гаджети.', 2, 'Гаджети за него', 'Идеи за технологични подаръци за него.'
  UNION ALL SELECT 'za-doma', 'Декорация за дома', 'dekoratsiya-za-doma', 'Декоративни подаръци за уютен дом.', 1, 'Декорация за дома', 'Идеи за декорация и подаръци за дома.'
  UNION ALL SELECT 'povodi', 'Коледа', 'koleda', 'Подаръци за уютни коледни празници.', 4, 'Коледни подаръци', 'Открийте коледни подаръци за любимите хора.'
  UNION ALL SELECT 'povodi', 'Свети Валентин', 'sveti-valentin', 'Романтични подаръци за Свети Валентин.', 5, 'Подаръци за Свети Валентин', 'Романтични идеи за подарък за Свети Валентин.'
) AS child
JOIN categories AS parent ON parent.slug = child.parent_slug
ON DUPLICATE KEY UPDATE
  parent_id = VALUES(parent_id),
  name = VALUES(name),
  description = VALUES(description),
  is_active = 1,
  sort_order = VALUES(sort_order),
  seo_title = VALUES(seo_title),
  seo_description = VALUES(seo_description),
  deleted_at = NULL;
