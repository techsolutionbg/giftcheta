CREATE TABLE admin_preferences (
  admin_id BIGINT UNSIGNED NOT NULL,
  preference_key VARCHAR(80) NOT NULL,
  preference_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id, preference_key),
  CONSTRAINT fk_admin_preferences_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
