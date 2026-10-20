CREATE TABLE invoice_issuer_settings (
  setting_id TINYINT UNSIGNED NOT NULL,
  legal_name VARCHAR(190) NOT NULL DEFAULT '',
  company_id VARCHAR(40) NOT NULL DEFAULT '',
  vat_number VARCHAR(40) NOT NULL DEFAULT '',
  address VARCHAR(500) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(254) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_id),
  CONSTRAINT chk_invoice_issuer_singleton CHECK (setting_id=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO invoice_issuer_settings (setting_id) VALUES (1);

CREATE TABLE order_invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(40) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  customer_requested TINYINT(1) NOT NULL DEFAULT 0,
  recipient_email VARCHAR(254) NOT NULL,
  snapshot_json JSON NOT NULL,
  private_file_path VARCHAR(500) NULL,
  issued_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_invoice_order (order_id),
  UNIQUE KEY uq_order_invoice_number (invoice_number),
  CONSTRAINT fk_order_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT chk_order_invoice_status CHECK (status IN ('draft','issued'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_sequences (
  sequence_year SMALLINT UNSIGNED NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (sequence_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_email_outbox
  ADD COLUMN attachment_path VARCHAR(500) NULL AFTER body_html,
  ADD COLUMN attachment_name VARCHAR(190) NULL AFTER attachment_path;
