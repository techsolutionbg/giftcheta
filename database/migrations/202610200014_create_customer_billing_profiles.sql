CREATE TABLE customer_billing_profiles (
  customer_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(16) NOT NULL DEFAULT 'person',
  recipient_name VARCHAR(160) NOT NULL,
  company_name VARCHAR(190) NULL,
  company_id VARCHAR(32) NULL,
  vat_number VARCHAR(32) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'BG',
  city VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NULL,
  address_line1 VARCHAR(200) NOT NULL,
  address_line2 VARCHAR(200) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  CONSTRAINT fk_customer_billing_profile_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT chk_customer_billing_profile_type CHECK (entity_type IN ('person','company')),
  CONSTRAINT chk_customer_billing_company CHECK (entity_type <> 'company' OR (company_name IS NOT NULL AND company_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
