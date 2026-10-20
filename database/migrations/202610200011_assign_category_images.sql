-- Give categories an initial image from their own products when possible.
-- Categories without products receive a deterministic pick from the active catalog,
-- so administrators can replace these starter images with dedicated artwork later.
UPDATE categories AS c
SET image_path = COALESCE(
  (
    SELECT i.file_path
    FROM product_categories AS pc
    JOIN products AS p ON p.id = pc.product_id
    JOIN product_images AS i ON i.product_id = p.id
    WHERE pc.category_id = c.id
      AND p.status = 'active'
      AND p.deleted_at IS NULL
    ORDER BY CRC32(CONCAT(c.slug, ':', p.id, ':', i.id))
    LIMIT 1
  ),
  (
    SELECT i.file_path
    FROM product_images AS i
    JOIN products AS p ON p.id = i.product_id
    WHERE p.status = 'active'
      AND p.deleted_at IS NULL
    ORDER BY CRC32(CONCAT(c.slug, ':', p.id, ':', i.id))
    LIMIT 1
  ),
  c.image_path
)
WHERE c.image_path IS NULL;
