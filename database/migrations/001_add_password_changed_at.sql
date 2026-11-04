-- 001: track when a password last changed, so that sessions created before
-- a password reset can be invalidated. (Already included in schema.sql for
-- fresh installs; run this only on databases created before it was added.)
ALTER TABLE users
    ADD COLUMN password_changed_at DATETIME NULL AFTER email_verified_at;
