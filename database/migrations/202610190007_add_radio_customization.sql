ALTER TABLE product_customization_fields
  DROP CHECK chk_custom_field_type,
  ADD CONSTRAINT chk_custom_field_type CHECK (field_type IN ('text','textarea','image','select','radio','checkbox'));

ALTER TABLE cart_item_customizations
  DROP CHECK chk_cart_customization_type,
  ADD CONSTRAINT chk_cart_customization_type CHECK (field_type IN ('text','textarea','image','select','radio','checkbox'));
