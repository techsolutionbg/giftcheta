CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NULL,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NULL,
  registered_email VARCHAR(254) GENERATED ALWAYS AS (CASE WHEN password_hash IS NULL THEN NULL ELSE LOWER(email) END) STORED,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  admin_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_registered_email (registered_email),
  KEY idx_customers_email (email),
  KEY idx_customers_phone (phone),
  KEY idx_customers_status_created (status, created_at),
  KEY idx_customers_deleted_created (deleted_at, created_at),
  KEY idx_customers_name (last_name, first_name),
  CONSTRAINT chk_customers_status CHECK (status IN ('active','inactive','blocked')),
  CONSTRAINT chk_registered_customer_has_email CHECK (password_hash IS NULL OR email IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  recipient_name VARCHAR(160) NOT NULL,
  phone VARCHAR(32) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'BG',
  city VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NULL,
  address_line1 VARCHAR(200) NOT NULL,
  address_line2 VARCHAR(200) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  default_customer_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_default = 1 THEN customer_id ELSE NULL END) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_addresses_default (default_customer_id),
  KEY idx_customer_addresses_customer (customer_id, is_default, id),
  CONSTRAINT fk_customer_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT chk_customer_addresses_default CHECK (is_default IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name) VALUES
  ('customers.view'),('customers.create'),('customers.update'),('customers.delete'),('customers.restore'),('customers.manage_status');
