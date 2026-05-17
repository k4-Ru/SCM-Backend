ALTER TABLE shipments
  ADD COLUMN warehouse_user_id INT NULL AFTER procurement_id,
  ADD KEY fk_shipments_warehouse_user (warehouse_user_id),
  ADD CONSTRAINT fk_shipments_warehouse_user
    FOREIGN KEY (warehouse_user_id) REFERENCES users(id)
    ON DELETE SET NULL;
