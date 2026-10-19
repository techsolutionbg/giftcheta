INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
SELECT parent.id, child.name, child.slug, child.description, 1, child.sort_order, child.seo_title, child.seo_description
FROM (
  SELECT 'personalizirani-podaratsi' AS parent_slug, 'Чаши и аксесоари' AS name, 'personalizirani-chashi-aksesoari' AS slug, 'Персонализирани чаши и аксесоари с лично послание.' AS description, 3 AS sort_order, 'Персонализирани чаши и аксесоари' AS seo_title, 'Подаръци с име, снимка или лично послание.' AS seo_description
  UNION ALL SELECT 'personalizirani-podaratsi', 'Текстил с послание', 'tekstil-s-poslanie', 'Текстилни подаръци с персонален надпис или изображение.', 4, 'Персонализиран текстил', 'Текстилни подаръци с лично послание.'
  UNION ALL SELECT 'podaratsi-za-neya', 'Козметика и грижа', 'kozmetika-i-grizha-za-neya', 'Красиви подаръци за грижа и добро настроение.', 3, 'Козметика и грижа за нея', 'Идеи за подаръци за красота и грижа.'
  UNION ALL SELECT 'podaratsi-za-neya', 'Романтични подаръци', 'romantichni-podaratsi-za-neya', 'Романтични изненади за любимата жена.', 4, 'Романтични подаръци за нея', 'Романтични идеи за подарък.'
  UNION ALL SELECT 'podaratsi-za-nego', 'Практични подаръци', 'praktichni-podaratsi-za-nego', 'Практични подаръци за ежедневието и свободното време.', 3, 'Практични подаръци за него', 'Практични идеи за подарък за него.'
  UNION ALL SELECT 'podaratsi-za-nego', 'За любителя на технологиите', 'tehnologichni-podaratsi-za-nego', 'Интересни технологични подаръци и аксесоари.', 4, 'Технологични подаръци за него', 'Гаджети и технологични подаръци.'
  UNION ALL SELECT 'za-doma', 'Свещи и аромати', 'sveshti-i-aromati-za-doma', 'Ароматни свещи и уютни акценти за дома.', 2, 'Свещи и аромати за дома', 'Подаръци за уютен и ароматен дом.'
  UNION ALL SELECT 'za-doma', 'Кухня и сервиране', 'kuhnya-i-servirane', 'Подаръци и красиви аксесоари за кухнята.', 3, 'Подаръци за кухнята', 'Идеи за подаръци за кухня и сервиране.'
  UNION ALL SELECT 'promotsii', 'Подаръци с намаление', 'promotsii-podaratsi-s-namalenie', 'Подаръци на специални цени.', 1, 'Подаръци с намаление', 'Разгледайте подаръците на специални цени.'
  UNION ALL SELECT 'promotsii', 'Последни предложения', 'promotsii-posledni-predlozheniya', 'Актуални оферти и специални предложения.', 2, 'Последни предложения', 'Вижте последните предложения и оферти.'
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
