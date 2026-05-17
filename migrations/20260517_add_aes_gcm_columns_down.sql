ALTER TABLE supplier_applications
  DROP COLUMN phone_tag,
  DROP COLUMN phone_iv,
  DROP COLUMN phone_enc;

ALTER TABLE users
  DROP COLUMN email_tag,
  DROP COLUMN email_iv,
  DROP COLUMN email_enc;

