-- 002: reports can target an item and/or a user (suspicious activity, misuse).
-- Already included in schema.sql for fresh installs.
ALTER TABLE reports
    MODIFY item_id INT UNSIGNED NULL,
    ADD COLUMN reported_user_id INT UNSIGNED NULL AFTER item_id,
    MODIFY reason ENUM('spam','fake','inappropriate','duplicate','suspicious','harassment','scam','other') NOT NULL,
    ADD KEY idx_reports_reported_user (reported_user_id),
    ADD CONSTRAINT fk_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE CASCADE;
