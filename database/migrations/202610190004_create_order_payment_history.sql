CREATE TABLE order_payment_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  previous_payment_status VARCHAR(24) NOT NULL,
  new_payment_status VARCHAR(24) NOT NULL,
  changed_by_admin_id BIGINT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_payment_history_order (order_id, created_at, id),
  CONSTRAINT fk_order_payment_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_payment_history_admin FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT chk_order_payment_history_from CHECK (previous_payment_status IN ('unpaid','paid','refunded')),
  CONSTRAINT chk_order_payment_history_to CHECK (new_payment_status IN ('unpaid','paid','refunded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
