ALTER TABLE products
  ADD COLUMN default_variant_id BIGINT UNSIGNED NULL AFTER sale_price,
  ADD KEY idx_products_default_variant (default_variant_id);

UPDATE products p
SET default_variant_id = (
  SELECT v.id
  FROM product_variants v
  WHERE v.product_id = p.id AND v.is_active = 1
  ORDER BY (v.is_available = 1 AND (v.inventory_mode = 'boolean' OR COALESCE(v.stock_quantity, 0) > 0)) DESC,
           v.is_available DESC,
           v.id
  LIMIT 1
)
WHERE EXISTS (
  SELECT 1 FROM product_variants v
  WHERE v.product_id = p.id AND v.is_active = 1
);
