ALTER TABLE categories
  ADD KEY idx_categories_public (is_active, deleted_at, parent_id, sort_order, id);

ALTER TABLE products
  ADD KEY idx_products_public (status, deleted_at, name, id);

CREATE TABLE seo_url_redirects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  old_path VARCHAR(512) NOT NULL,
  new_path VARCHAR(512) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_redirect_old_path (old_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
