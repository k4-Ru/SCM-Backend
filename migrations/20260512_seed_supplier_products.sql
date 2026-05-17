-- Seed supplier_products mappings (idempotent)
INSERT INTO supplier_products (supplier_id, product_id, unit_price) VALUES
  (1, 1, 210.00),
  (1, 2, 98.00),
  (1, 5, 130.00),
  (2, 3, 720.00),
  (2, 4, 480.00),
  (2, 10, 450.00),
  (3, 6, 1100.00),
  (3, 7, 100.00),
  (3, 8, 60.00),
  (4, 9, 120.00),
  (4, 11, 200.00),
  (5, 1, 210.00),
  (5, 5, 130.00),
  (5, 7, 100.00)
ON DUPLICATE KEY UPDATE
  unit_price = VALUES(unit_price),
  updated_at = CURRENT_TIMESTAMP;
