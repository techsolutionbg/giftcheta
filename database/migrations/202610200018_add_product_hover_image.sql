ALTER TABLE product_images
  ADD COLUMN is_hover TINYINT(1) NOT NULL DEFAULT 0 AFTER is_primary,
  ADD KEY idx_product_images_hover (product_id, is_hover);
