ALTER TABLE products
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
  ADD KEY idx_products_deleted_at_updated (deleted_at, updated_at, id);
