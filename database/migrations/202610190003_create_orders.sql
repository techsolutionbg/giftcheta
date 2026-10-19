CREATE TABLE orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(32) NOT NULL,
  idempotency_key CHAR(64) NULL,
  idempotency_hash CHAR(64) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  customer_email VARCHAR(254) NOT NULL,
  customer_phone VARCHAR(32) NULL,
  customer_first_name VARCHAR(100) NOT NULL,
  customer_last_name VARCHAR(100) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'new',
  payment_status VARCHAR(24) NOT NULL DEFAULT 'unpaid',
  fulfillment_status VARCHAR(24) NOT NULL DEFAULT 'unfulfilled',
  payment_method VARCHAR(32) NOT NULL DEFAULT 'cash_on_delivery',
  shipping_method VARCHAR(100) NOT NULL DEFAULT 'Ръчно определена доставка',
  currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  customer_note TEXT NULL,
  admin_note TEXT NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  stock_restored_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  UNIQUE KEY uq_orders_idempotency (idempotency_key),
  KEY idx_orders_customer_created (customer_id, created_at),
  KEY idx_orders_created (created_at, id),
  KEY idx_orders_status_created (status, created_at, id),
  KEY idx_orders_payment_created (payment_status, created_at, id),
  KEY idx_orders_fulfillment_created (fulfillment_status, created_at, id),
  KEY idx_orders_email (customer_email),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_created_admin FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT chk_orders_status CHECK (status IN ('new','confirmed','processing','shipped','delivered','cancelled')),
  CONSTRAINT chk_orders_payment_status CHECK (payment_status IN ('unpaid','paid','refunded')),
  CONSTRAINT chk_orders_fulfillment_status CHECK (fulfillment_status IN ('unfulfilled','in_progress','shipped','fulfilled')),
  CONSTRAINT chk_orders_payment_method CHECK (payment_method = 'cash_on_delivery'),
  CONSTRAINT chk_orders_currency CHECK (currency_code = 'EUR'),
  CONSTRAINT chk_orders_totals CHECK (subtotal >= 0 AND discount_total >= 0 AND shipping_total >= 0 AND tax_total >= 0 AND grand_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  sku VARCHAR(100) NULL,
  variant_name VARCHAR(190) NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  customization_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id, id),
  KEY idx_order_items_product (product_id),
  KEY idx_order_items_variant (product_variant_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_order_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
  CONSTRAINT chk_order_items_quantity CHECK (quantity > 0),
  CONSTRAINT chk_order_items_totals CHECK (unit_price >= 0 AND customization_total >= 0 AND discount_total >= 0 AND line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  address_type VARCHAR(16) NOT NULL DEFAULT 'shipping',
  recipient_name VARCHAR(160) NOT NULL,
  phone VARCHAR(32) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'BG',
  city VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NULL,
  address_line1 VARCHAR(200) NOT NULL,
  address_line2 VARCHAR(200) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_addresses_order (order_id, address_type),
  CONSTRAINT fk_order_addresses_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT chk_order_addresses_type CHECK (address_type IN ('shipping','billing'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_item_customizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_item_id BIGINT UNSIGNED NOT NULL,
  field_identifier VARCHAR(160) NOT NULL,
  field_label VARCHAR(160) NOT NULL,
  field_type VARCHAR(30) NOT NULL,
  value_text MEDIUMTEXT NULL,
  file_reference VARCHAR(500) NULL,
  price_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_customizations_item (order_item_id, id),
  CONSTRAINT fk_order_customizations_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
  CONSTRAINT chk_order_customization_type CHECK (field_type IN ('text','textarea','image','select','checkbox')),
  CONSTRAINT chk_order_customization_price CHECK (price_adjustment >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(24) NULL,
  new_status VARCHAR(24) NOT NULL,
  changed_by_admin_id BIGINT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_status_history_order (order_id, created_at, id),
  CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_status_history_admin FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_email_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  event_key VARCHAR(80) NOT NULL,
  recipient_email VARCHAR(254) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  body_text MEDIUMTEXT NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  sent_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_email_event (order_id, event_key),
  KEY idx_order_email_retry (status, next_attempt_at, locked_at, id),
  CONSTRAINT fk_order_email_outbox_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT chk_order_email_status CHECK (status IN ('pending','sending','sent','failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name) VALUES
  ('orders.view'),('orders.create'),('orders.update'),('orders.change_status'),('orders.manage_payment'),('orders.cancel'),('orders.view_history');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT rp.role_id, added.id
FROM role_permissions rp
JOIN permissions existing ON existing.id=rp.permission_id AND existing.name IN ('*','products.write')
JOIN permissions added ON added.name LIKE 'orders.%'
GROUP BY rp.role_id,added.id;
