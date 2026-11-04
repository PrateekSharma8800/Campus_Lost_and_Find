-- =====================================================================
-- CampusFind database schema
-- MySQL 8+ / InnoDB / utf8mb4
--
-- Load into an EMPTY database:
--   mysql -u campusfind -p campusfind < database/schema.sql
--
-- CREATE TABLE is used without IF NOT EXISTS on purpose: re-running this
-- against a database that already has tables fails loudly instead of
-- silently leaving an outdated structure in place. Later changes go in
-- database/migrations/.
--
-- All timestamps are stored in UTC (the app sets the session time_zone).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- users: students, staff and admins
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                  VARCHAR(100) NOT NULL,
    email                 VARCHAR(190) NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    phone                 VARCHAR(20)  NULL,
    student_id            VARCHAR(50)  NULL,
    role                  ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
    is_active             TINYINT(1)   NOT NULL DEFAULT 1,
    email_verified_at     DATETIME     NULL,
    password_changed_at   DATETIME     NULL,    -- sessions started before this are logged out
    last_login_at         DATETIME     NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- categories: lookup list (Electronics, Keys, ...)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL,
    slug        VARCHAR(60)  NOT NULL,
    icon        VARCHAR(40)  NULL,          -- Bootstrap Icons class, e.g. "bi-laptop"
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- locations: lookup list of campus places
-- ---------------------------------------------------------------------
CREATE TABLE locations (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(100) NOT NULL,
    building     VARCHAR(100) NULL,
    description  VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- items: every lost or found post
-- ---------------------------------------------------------------------
CREATE TABLE items (
    id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED      NOT NULL,
    type             ENUM('lost','found') NOT NULL,
    title            VARCHAR(150)      NOT NULL,
    description      TEXT              NOT NULL,
    category_id      SMALLINT UNSIGNED NOT NULL,
    location_id      SMALLINT UNSIGNED NOT NULL,
    location_detail  VARCHAR(255)      NULL,      -- "2nd floor, near the printers"
    date_occurred    DATE              NOT NULL,  -- date lost / date found
    status           ENUM('open','claimed','returned','closed') NOT NULL DEFAULT 'open',
    is_approved      TINYINT(1)        NOT NULL DEFAULT 1,  -- admin can hide a post (0)
    secret_detail    VARCHAR(255)      NULL,      -- found items only: hidden feature used to verify claims
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_items_type_status (type, status, is_approved),
    KEY idx_items_match (type, category_id, date_occurred),   -- Possible Matches lookups
    KEY idx_items_user (user_id),
    KEY idx_items_category (category_id),
    KEY idx_items_location (location_id),
    FULLTEXT KEY ft_items_search (title, description),
    CONSTRAINT fk_items_user     FOREIGN KEY (user_id)     REFERENCES users (id)      ON DELETE CASCADE,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations (id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- item_images: up to 3 photos per item (limit enforced in the app)
-- file_name is a random name generated by the server, never the
-- user's original filename. Files live in storage/uploads/items/.
-- ---------------------------------------------------------------------
CREATE TABLE item_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id     INT UNSIGNED NOT NULL,
    file_name   VARCHAR(100) NOT NULL,
    mime_type   ENUM('image/jpeg','image/png','image/webp') NOT NULL,
    file_size   INT UNSIGNED NOT NULL,
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_item_images_file (file_name),
    KEY idx_item_images_item (item_id),
    CONSTRAINT chk_item_images_size CHECK (file_size > 0),
    CONSTRAINT fk_item_images_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- claims: "this item is mine" requests
-- ---------------------------------------------------------------------
CREATE TABLE claims (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id       INT UNSIGNED NOT NULL,
    claimant_id   INT UNSIGNED NOT NULL,
    message       TEXT         NOT NULL,
    proof_answer  VARCHAR(255) NULL,          -- answer to the item's secret_detail question
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by   INT UNSIGNED NULL,
    reviewed_at   DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_claims_item_claimant (item_id, claimant_id),  -- one claim per person per item
    KEY idx_claims_claimant (claimant_id),
    KEY idx_claims_status (status),
    CONSTRAINT fk_claims_item     FOREIGN KEY (item_id)     REFERENCES items (id) ON DELETE CASCADE,
    CONSTRAINT fk_claims_claimant FOREIGN KEY (claimant_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_claims_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- messages: private messages between two users about one item
-- (sender <> receiver is enforced in the app: MySQL does not allow a
-- CHECK constraint on columns used by an ON DELETE CASCADE foreign key)
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id      INT UNSIGNED NOT NULL,
    sender_id    INT UNSIGNED NOT NULL,
    receiver_id  INT UNSIGNED NOT NULL,
    body         TEXT         NOT NULL,
    read_at      DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_thread (item_id, sender_id, receiver_id, created_at),
    KEY idx_messages_inbox (receiver_id, read_at),
    CONSTRAINT fk_messages_item     FOREIGN KEY (item_id)     REFERENCES items (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender   FOREIGN KEY (sender_id)   REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notifications: in-app alerts
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(40)  NOT NULL,       -- claim_received, claim_approved, new_message, possible_match ...
    message     VARCHAR(255) NOT NULL,
    link        VARCHAR(255) NULL,           -- app-relative path, e.g. /items/12
    read_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- password_resets: single-use reset tokens
-- Only the SHA-256 hash of the token is stored, so a database leak
-- does not expose usable reset links.
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reports: users flag inappropriate or fake posts
-- ---------------------------------------------------------------------
CREATE TABLE reports (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id      INT UNSIGNED NOT NULL,
    reporter_id  INT UNSIGNED NOT NULL,
    reason       ENUM('spam','fake','inappropriate','duplicate','other') NOT NULL,
    details      VARCHAR(500) NULL,
    status       ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    reviewed_by  INT UNSIGNED NULL,
    reviewed_at  DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reports_item_reporter (item_id, reporter_id),  -- one report per person per item
    KEY idx_reports_status (status),
    CONSTRAINT fk_reports_item     FOREIGN KEY (item_id)     REFERENCES items (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- login_attempts: failed logins, used to rate-limit brute-force attacks
-- (no FK: attempts are recorded even for emails that don't exist)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(190)    NOT NULL,
    ip_address    VARCHAR(45)     NOT NULL,   -- fits IPv6
    attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email (email, attempted_at),
    KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
