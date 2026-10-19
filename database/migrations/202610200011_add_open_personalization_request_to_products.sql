INSERT INTO product_customization_fields (product_id, name, field_type, is_required, position, validation_rules, options, additional_price, is_active)
SELECT p.id,
       'Опишете желаната персонализация',
       'textarea',
       0,
       COALESCE((SELECT MAX(existing.position) + 1 FROM product_customization_fields existing WHERE existing.product_id = p.id), 0),
       JSON_OBJECT('max_length', 500),
       NULL,
       0.00,
       1
FROM products p
WHERE p.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1
    FROM product_customization_fields existing
    WHERE existing.product_id = p.id
      AND existing.name = 'Опишете желаната персонализация'
  );
