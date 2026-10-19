CREATE TABLE carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NULL,
  guest_key_hash CHAR(64) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_guest_key_hash (guest_key_hash),
  KEY idx_carts_customer_status (customer_id,status,updated_at),
  KEY idx_carts_expiration (status,expires_at),
  CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT chk_carts_status CHECK (status IN ('active','abandoned','converted'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  item_key CHAR(32) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  product_name_snapshot VARCHAR(190) NOT NULL,
  variant_name_snapshot VARCHAR(190) NULL,
  customization_hash CHAR(64) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_items_public_key (item_key),
  KEY idx_cart_items_cart (cart_id,id),
  KEY idx_cart_items_product_variant (product_id,product_variant_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  CONSTRAINT chk_cart_item_quantity CHECK (quantity BETWEEN 1 AND 999),
  CONSTRAINT chk_cart_item_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_item_customizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_item_id BIGINT UNSIGNED NOT NULL,
  customization_field_id BIGINT UNSIGNED NULL,
  field_name_snapshot VARCHAR(160) NOT NULL,
  field_type VARCHAR(30) NOT NULL,
  value_text MEDIUMTEXT NULL,
  file_reference VARCHAR(80) NULL,
  price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_item_customization_field (cart_item_id,customization_field_id),
  KEY idx_cart_customization_file (file_reference),
  CONSTRAINT fk_cart_customization_item FOREIGN KEY (cart_item_id) REFERENCES cart_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_customization_field FOREIGN KEY (customization_field_id) REFERENCES product_customization_fields(id) ON DELETE SET NULL,
  CONSTRAINT chk_cart_customization_type CHECK (field_type IN ('text','textarea','image','select','radio','checkbox')),
  CONSTRAINT chk_cart_customization_price CHECK (price_adjustment >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_uploads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  customization_field_id BIGINT UNSIGNED NULL,
  cart_item_id BIGINT UNSIGNED NULL,
  file_reference VARCHAR(80) NOT NULL,
  content_hash CHAR(64) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_upload_reference (file_reference),
  KEY idx_cart_upload_rate (cart_id,created_at),
  KEY idx_cart_upload_cleanup (created_at,cart_item_id),
  CONSTRAINT fk_cart_upload_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_upload_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_upload_field FOREIGN KEY (customization_field_id) REFERENCES product_customization_fields(id) ON DELETE SET NULL,
  CONSTRAINT fk_cart_upload_item FOREIGN KEY (cart_item_id) REFERENCES cart_items(id) ON DELETE CASCADE,
  CONSTRAINT chk_cart_upload_type CHECK (mime_type IN ('image/jpeg','image/png','image/webp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
