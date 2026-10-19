CREATE TABLE store_settings (
  setting_id TINYINT UNSIGNED NOT NULL,
  free_shipping_enabled TINYINT(1) NOT NULL DEFAULT 1,
  free_shipping_threshold DECIMAL(10,2) NOT NULL DEFAULT 40.90,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_id),
  CONSTRAINT chk_store_settings_singleton CHECK (setting_id = 1),
  CONSTRAINT chk_free_shipping_threshold CHECK (free_shipping_threshold >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO store_settings (setting_id, free_shipping_enabled, free_shipping_threshold)
VALUES (1, 1, 40.90);
