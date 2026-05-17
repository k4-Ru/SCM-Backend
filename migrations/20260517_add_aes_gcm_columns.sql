ALTER TABLE users
  ADD COLUMN email_enc TEXT NULL AFTER email,
  ADD COLUMN email_iv VARCHAR(24) NULL AFTER email_enc,
  ADD COLUMN email_tag VARCHAR(24) NULL AFTER email_iv;

ALTER TABLE supplier_applications
  ADD COLUMN phone_enc TEXT NULL AFTER phone,
  ADD COLUMN phone_iv VARCHAR(24) NULL AFTER phone_enc,
  ADD COLUMN phone_tag VARCHAR(24) NULL AFTER phone_iv;

