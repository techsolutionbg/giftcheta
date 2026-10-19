INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
SELECT parent.id, child.name, child.slug, child.description, 1, child.sort_order, child.seo_title, child.seo_description
FROM (
  SELECT 'Букети от рози' AS name, 'buketi-ot-rozi' AS slug, 'Романтични букети от рози за специалните моменти.' AS description, 1 AS sort_order, 'Букети от рози' AS seo_title, 'Подаръчни букети от рози за любим човек.' AS seo_description
  UNION ALL SELECT 'Празнични букети', 'praznichni-buketi', 'Цветни букети за празници и важни поводи.', 2, 'Празнични букети', 'Букети за празници и специални поводи.'
  UNION ALL SELECT 'Декоративни цветя', 'dekorativni-cvetya', 'Декоративни цветя за красив и дълготраен подарък.', 3, 'Декоративни цветя', 'Декоративни цветя и букети за подарък.'
) AS child
JOIN categories AS parent ON parent.slug = 'tsvetya-i-buketi' AND parent.is_active = 1 AND parent.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
  parent_id = VALUES(parent_id),
  name = VALUES(name),
  description = VALUES(description),
  is_active = 1,
  sort_order = VALUES(sort_order),
  seo_title = VALUES(seo_title),
  seo_description = VALUES(seo_description),
  deleted_at = NULL;
