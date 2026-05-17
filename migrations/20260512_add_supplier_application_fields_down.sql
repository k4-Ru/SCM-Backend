-- Rollback supplier application onboarding fields
ALTER TABLE supplier_applications
  DROP COLUMN password_hash,
  DROP COLUMN documents_json,
  DROP COLUMN document_path,
  DROP COLUMN document_name,
  DROP COLUMN products_offered,
  DROP COLUMN contact_number;
