ALTER TABLE product_variants
  ADD COLUMN image_path VARCHAR(512) NULL AFTER color_hex,
  ADD COLUMN image_thumbnail_path VARCHAR(512) NULL AFTER image_path;
