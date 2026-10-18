CREATE TABLE customer_password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(254) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_password_reset_token (token_hash),
  KEY idx_customer_password_reset_customer_created (customer_id, created_at),
  KEY idx_customer_password_reset_expiry (expires_at),
  CONSTRAINT fk_customer_password_reset_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name) VALUES ('customers.reset_password');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rp.role_id, reset_permission.id
  FROM role_permissions rp
  JOIN permissions granted_permission ON granted_permission.id = rp.permission_id
  JOIN permissions reset_permission ON reset_permission.name = 'customers.reset_password'
  WHERE granted_permission.name IN ('*', 'customers.update');
