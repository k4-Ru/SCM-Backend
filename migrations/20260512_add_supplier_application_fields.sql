-- Add supplier application fields for richer onboarding submissions
ALTER TABLE supplier_applications
  ADD COLUMN contact_number VARCHAR(30) NULL AFTER phone,
  ADD COLUMN products_offered TEXT NULL AFTER address,
  ADD COLUMN document_name VARCHAR(255) NULL AFTER products_offered,
  ADD COLUMN document_path VARCHAR(255) NULL AFTER document_name,
  ADD COLUMN documents_json LONGTEXT NULL AFTER document_path,
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER documents_json;
