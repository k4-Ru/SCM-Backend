-- Rollback seeded supplier_products mappings
DELETE FROM supplier_products
WHERE (supplier_id = 1 AND product_id IN (1, 2, 5))
   OR (supplier_id = 2 AND product_id IN (3, 4, 10))
   OR (supplier_id = 3 AND product_id IN (6, 7, 8))
   OR (supplier_id = 4 AND product_id IN (9, 11))
   OR (supplier_id = 5 AND product_id IN (1, 5, 7));
