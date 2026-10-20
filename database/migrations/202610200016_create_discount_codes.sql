CREATE TABLE discount_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  discount_type VARCHAR(16) NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  minimum_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  maximum_uses INT UNSIGNED NULL,
  uses_count INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_discount_codes_code (code),
  KEY idx_discount_codes_active_dates (is_active,starts_at,ends_at),
  CONSTRAINT chk_discount_codes_type CHECK (discount_type IN ('percentage','fixed')),
  CONSTRAINT chk_discount_codes_value CHECK (discount_value > 0),
  CONSTRAINT chk_discount_codes_subtotal CHECK (minimum_subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE carts
  ADD COLUMN discount_code_id BIGINT UNSIGNED NULL AFTER customer_id,
  ADD KEY idx_carts_discount_code (discount_code_id),
  ADD CONSTRAINT fk_carts_discount_code FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE SET NULL;

ALTER TABLE orders
  ADD COLUMN discount_code_id BIGINT UNSIGNED NULL AFTER customer_id,
  ADD COLUMN discount_code_snapshot VARCHAR(64) NULL AFTER discount_code_id,
  ADD KEY idx_orders_discount_code (discount_code_id),
  ADD CONSTRAINT fk_orders_discount_code FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE SET NULL;

CREATE TABLE discount_code_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  discount_code_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  discount_amount DECIMAL(12,2) NOT NULL,
  redeemed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_discount_redemptions_order (order_id),
  KEY idx_discount_redemptions_code (discount_code_id,redeemed_at),
  CONSTRAINT fk_discount_redemptions_code FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_discount_redemptions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_redemptions_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT chk_discount_redemptions_amount CHECK (discount_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name) VALUES ('discount_codes.read'), ('discount_codes.write');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT rp.role_id, p.id FROM role_permissions rp
JOIN permissions existing ON existing.id=rp.permission_id AND existing.name IN ('*','products.write')
JOIN permissions p ON p.name IN ('discount_codes.read','discount_codes.write');
