CREATE TABLE product_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  author_name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product_reviews_public (product_id, status, created_at, id),
  CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT chk_product_reviews_status CHECK (status IN ('published','hidden'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
