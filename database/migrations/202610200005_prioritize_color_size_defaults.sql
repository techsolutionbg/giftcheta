UPDATE products p
SET default_variant_id = (
  SELECT v.id
  FROM product_variants v
  WHERE v.product_id = p.id AND v.is_active = 1
  ORDER BY (
    JSON_CONTAINS_PATH(v.option_values, 'one', '$."Цвят"', '$."цвят"', '$."Color"', '$."color"')
    AND JSON_CONTAINS_PATH(v.option_values, 'one', '$."Размер"', '$."размер"', '$."Size"', '$."size"')
  ) DESC,
  (v.is_available = 1 AND (v.inventory_mode = 'boolean' OR COALESCE(v.stock_quantity, 0) > 0)) DESC,
  v.is_available DESC,
  v.id
  LIMIT 1
)
WHERE EXISTS (
  SELECT 1 FROM product_variants v
  WHERE v.product_id = p.id AND v.is_active = 1
);
