INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
SELECT parent.id, child.name, child.slug, child.description, 1, child.sort_order, child.seo_title, child.seo_description
FROM (
  SELECT 'personalizirani-podaratsi' AS parent_slug, 'Подаръци със снимка' AS name, 'podaratsi-sas-snimka' AS slug, 'Подаръци с любими снимки и спомени.' AS description, 5 AS sort_order, 'Подаръци със снимка' AS seo_title, 'Персонализирани подаръци със снимка.' AS seo_description
  UNION ALL SELECT 'personalizirani-podaratsi', 'Картички с лично послание', 'kartichki-lichno-poslanie', 'Картички с лично послание за специален човек.', 6, 'Картички с лично послание', 'Персонални картички за специални поводи.'
  UNION ALL SELECT 'podaratsi-za-neya', 'Цветя и романтични изненади', 'cvetya-romantichni-iznenadi', 'Цветя и романтични подаръци за нея.', 5, 'Цветя и романтични изненади', 'Романтични изненади и цветя за нея.'
  UNION ALL SELECT 'podaratsi-za-neya', 'Мода и стил', 'moda-i-stil-za-neya', 'Стилни подаръци и аксесоари за нея.', 6, 'Мода и стил за нея', 'Елегантни модни подаръци и аксесоари.'
  UNION ALL SELECT 'podaratsi-za-nego', 'Кожени аксесоари', 'kozheni-aksesoari-za-nego', 'Практични кожени аксесоари за него.', 5, 'Кожени аксесоари за него', 'Подаръчни кожени аксесоари за него.'
  UNION ALL SELECT 'podaratsi-za-nego', 'За хоби и свободно време', 'podaratsi-hobi-svobodno-vreme', 'Подаръци за любими занимания и свободно време.', 6, 'Подаръци за хоби и свободно време', 'Идеи за подаръци според хобито му.'
  UNION ALL SELECT 'za-doma', 'Текстил за дома', 'tekstil-za-doma', 'Меки и красиви текстилни акценти за уютен дом.', 4, 'Текстил за дома', 'Подаръци и текстилни акценти за дома.'
  UNION ALL SELECT 'za-doma', 'Настолни игри', 'nastolni-igri-za-doma', 'Настолни игри за споделени моменти у дома.', 5, 'Настолни игри за дома', 'Игри и забавления за цялото семейство.'
  UNION ALL SELECT 'povodi', 'Подаръци за нов дом', 'podaratsi-za-nov-dom', 'Подаръци за нов дом и ново начало.', 6, 'Подаръци за нов дом', 'Идеи за подаръци за нов дом.'
  UNION ALL SELECT 'povodi', 'Дипломиране и успех', 'diplomirane-i-uspeh', 'Подаръци за дипломиране, постижения и нови начала.', 7, 'Подаръци за дипломиране', 'Празнувайте успеха с подходящ подарък.'
  UNION ALL SELECT 'povodi', 'Великден', 'velikden-podaratsi', 'Празнични подаръци за Великден.', 8, 'Великденски подаръци', 'Идеи за подаръци и изненади за Великден.'
  UNION ALL SELECT 'promotsii', 'Намалени комплекти', 'namaleni-podarachni-komplekti', 'Подаръчни комплекти на специални цени.', 3, 'Намалени подаръчни комплекти', 'Открийте изгодни подаръчни комплекти.'
  UNION ALL SELECT 'promotsii', 'Оферти до 50 евро', 'promotsii-do-50-evro', 'Избрани подаръци на цени до 50 евро.', 4, 'Оферти до 50 евро', 'Подаръци на специални цени до 50 евро.'
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
