INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
SELECT parent.id, child.name, child.slug, child.description, 1, child.sort_order, child.seo_title, child.seo_description
FROM (
  SELECT 'Нова' AS parent_name, 'nova' AS parent_slug, 'Нови предложения' AS name, 'nova-predlozheniya' AS slug, 'Най-новите предложения в магазина.' AS description, 1 AS sort_order, 'Нови предложения' AS seo_title, 'Разгледайте новите предложения в магазина.' AS seo_description
  UNION ALL SELECT 'Нова', 'nova', 'Нови подаръци', 'novi-podaratsi', 'Нови идеи за подарък за специалните хора.', 2, 'Нови подаръци', 'Открийте нови идеи за подарък.'
  UNION ALL SELECT 'Нова', 'nova', 'Нови персонализирани подаръци', 'novi-personalizirani-podaratsi', 'Нови подаръци с лично послание и персонален детайл.', 3, 'Нови персонализирани подаръци', 'Персонализирани подаръци в нови модели.'
  UNION ALL SELECT 'Подаръци за деца', 'podaratsi-za-detsa', 'Играчки и игри', 'igrachki-i-igri', 'Играчки и забавни игри за деца.', 1, 'Играчки и игри', 'Идеи за подарък с играчки и игри.'
  UNION ALL SELECT 'Подаръци за деца', 'podaratsi-za-detsa', 'Творчески комплекти', 'tvorcheski-komplekti-za-detsa', 'Комплекти за творчество и занимания за деца.', 2, 'Творчески комплекти за деца', 'Подаръци за творчество и въображение.'
  UNION ALL SELECT 'Подаръци за деца', 'podaratsi-za-detsa', 'Персонални подаръци за деца', 'personalni-podaratsi-za-detsa', 'Персонализирани подаръци за малки откриватели.', 3, 'Персонални подаръци за деца', 'Подаръци за деца с лично послание.'
  UNION ALL SELECT 'Подаръчни комплекти', 'podarachni-komplekti', 'Романтични комплекти', 'romantichni-komplekti', 'Подбрани комплекти за романтични поводи.', 1, 'Романтични подаръчни комплекти', 'Романтични комплекти за любим човек.'
  UNION ALL SELECT 'Подаръчни комплекти', 'podarachni-komplekti', 'Комплекти за дома', 'komplekti-za-doma', 'Подаръчни комплекти за уют и настроение у дома.', 2, 'Комплекти за дома', 'Подаръчни комплекти за дома.'
  UNION ALL SELECT 'Подаръчни комплекти', 'podarachni-komplekti', 'Тематични комплекти', 'tematichni-podarachni-komplekti', 'Комплекти за различни поводи и интереси.', 3, 'Тематични подаръчни комплекти', 'Тематични комплекти за всеки специален повод.'
) AS child
JOIN categories AS parent
  ON parent.is_active = 1
 AND parent.deleted_at IS NULL
 AND (parent.slug = child.parent_slug OR parent.name = child.parent_name)
ON DUPLICATE KEY UPDATE
  parent_id = VALUES(parent_id),
  name = VALUES(name),
  description = VALUES(description),
  is_active = 1,
  sort_order = VALUES(sort_order),
  seo_title = VALUES(seo_title),
  seo_description = VALUES(seo_description),
  deleted_at = NULL;
