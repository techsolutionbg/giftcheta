ALTER TABLE categories
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
  ADD KEY idx_categories_deleted_at_sort (deleted_at, sort_order, id);
