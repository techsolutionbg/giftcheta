CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admins_email (email), KEY idx_admins_role (role_id),
  CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_login_attempts (
  attempt_key CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  PRIMARY KEY (attempt_key), KEY idx_login_attempts_lock (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  image_path VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(320) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_categories_slug (slug), KEY idx_categories_parent_sort (parent_id, sort_order, id), KEY idx_categories_active (is_active),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  sku VARCHAR(100) NULL,
  short_description VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  inventory_mode VARCHAR(20) NOT NULL DEFAULT 'boolean',
  stock_quantity INT UNSIGNED NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(320) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_products_slug (slug), UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_status_updated (status, updated_at), KEY idx_products_name (name), KEY idx_products_available (is_available),
  CONSTRAINT chk_products_price CHECK (price >= 0), CONSTRAINT chk_products_sale_price CHECK (sale_price IS NULL OR sale_price >= 0),
  CONSTRAINT chk_products_inventory CHECK (inventory_mode IN ('tracked','boolean')),
  CONSTRAINT chk_products_status CHECK (status IN ('draft','active','archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id), KEY idx_product_categories_category (category_id, product_id),
  CONSTRAINT fk_product_categories_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  option_values JSON NOT NULL,
  variant_key CHAR(64) NOT NULL,
  sku VARCHAR(100) NULL,
  price DECIMAL(10,2) NULL,
  price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
  inventory_mode VARCHAR(20) NOT NULL DEFAULT 'boolean',
  stock_quantity INT UNSIGNED NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_variant_combination (product_id, variant_key), UNIQUE KEY uq_variants_sku (sku), KEY idx_variants_product (product_id, is_active),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT chk_variants_inventory CHECK (inventory_mode IN ('tracked','boolean')),
  CONSTRAINT chk_variants_price CHECK (price IS NULL OR price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  thumbnail_path VARCHAR(500) NULL,
  alt_text VARCHAR(190) NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_product_images_order (product_id, position, id),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_customization_fields (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  field_type VARCHAR(30) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  position INT NOT NULL DEFAULT 0,
  validation_rules JSON NULL,
  options JSON NULL,
  additional_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), KEY idx_custom_fields_product_order (product_id, position, id),
  CONSTRAINT fk_custom_fields_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT chk_custom_field_type CHECK (field_type IN ('text','textarea','image','select','checkbox')),
  CONSTRAINT chk_custom_field_price CHECK (additional_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_stock_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  quantity_delta INT NOT NULL,
  quantity_after INT UNSIGNED NULL,
  reason VARCHAR(40) NOT NULL,
  note VARCHAR(500) NULL,
  admin_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_stock_movements_product_time (product_id, created_at), KEY idx_stock_movements_variant_time (variant_id, created_at),
  CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_stock_movements_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_movements_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug) VALUES ('Administrator', 'administrator');
INSERT INTO permissions (name) VALUES ('*'),('products.read'),('products.write'),('products.delete'),('categories.read'),('categories.write'),('categories.delete'),('inventory.write');
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
