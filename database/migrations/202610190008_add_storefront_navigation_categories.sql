INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order, seo_title, seo_description)
VALUES
  (NULL, 'За дома', 'za-doma', 'Подаръци и декорации за дома.', 1, 11, 'Подаръци за дома', 'Идеи за подаръци и декорации за дома.'),
  (NULL, 'Поводи', 'povodi', 'Подаръци за важните поводи и специалните моменти.', 1, 12, 'Подаръци за поводи', 'Изберете подарък за рожден ден, сватба, бебе и други поводи.'),
  (NULL, 'Промоции', 'promotsii', 'Специални предложения и намалени подаръци.', 1, 13, 'Промоции', 'Разгледайте актуалните предложения.')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  is_active = 1,
  deleted_at = NULL;

UPDATE categories
SET parent_id = (SELECT id FROM (SELECT id FROM categories WHERE slug = 'povodi') AS parent_category)
WHERE slug IN ('podaratsi-za-rozhden-den', 'svatba-i-godishnina', 'bebeshki-podaratsi');
