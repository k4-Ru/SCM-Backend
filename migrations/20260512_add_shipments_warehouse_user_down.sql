ALTER TABLE shipments
  DROP FOREIGN KEY fk_shipments_warehouse_user,
  DROP KEY fk_shipments_warehouse_user,
  DROP COLUMN warehouse_user_id;
