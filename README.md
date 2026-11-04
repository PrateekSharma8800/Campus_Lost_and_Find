# CampusFind

Campus lost-and-found portal. Plain PHP 8 + PDO, MySQL (InnoDB, utf8mb4), Bootstrap 5.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `gd`, `fileinfo`, `mbstring`
- MySQL 8+

## Local setup

1. **Create the database and an app user** (run as MySQL root):

   ```sql
   CREATE DATABASE campusfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'campusfind'@'localhost' IDENTIFIED BY '<password>';
   CREATE USER 'campusfind'@'127.0.0.1' IDENTIFIED BY '<password>';
   GRANT ALL PRIVILEGES ON campusfind.*      TO 'campusfind'@'localhost', 'campusfind'@'127.0.0.1';
   GRANT ALL PRIVILEGES ON campusfind_test.* TO 'campusfind'@'localhost', 'campusfind'@'127.0.0.1';
   ```

2. **Configure:** `cp .env.example .env`, then set `DB_PASS`.

3. **Load schema and lookup data:**

   ```sh
   mysql -u campusfind -p campusfind < database/schema.sql
   mysql -u campusfind -p campusfind < database/seed.sql
   ```

4. **Create your admin account** (password is typed at a hidden prompt):
   `php bin/create-admin.php`

5. **Start the site:** `php -S localhost:8000 -t public public/index.php`
   and open <http://localhost:8000>.

6. **Run tests:** `php tests/run.php`
   (Tests rebuild the separate `campusfind_test` database each run and start their
   own server on a random port; the dev database is never touched.)

## Emails in local development

There is no mail server locally. Password-reset emails are written to
`storage/logs/mail.log`; open the link from there.

## Security summary

- Passwords: `password_hash()` (bcrypt) / `password_verify()`, 8+ chars with a letter and a number.
- SQL: PDO with real prepared statements only; stacked queries disabled.
- CSRF token on every POST form; checked centrally by the router.
- Sessions: HttpOnly + SameSite cookie, strict mode, ID regenerated on login,
  2 h idle / 12 h absolute timeout, all sessions ended on password reset or deactivation.
- Login rate limit: 5 failures per email or 20 per IP in 15 minutes.
- Password reset: single-use, 60-minute tokens stored only as SHA-256 hashes; no account enumeration.
- Headers: Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, no-store.
- `.env`, code, SQL and logs live outside `public/` and are never served.

## Layout

| Path | Purpose |
|---|---|
| `public/` | Web root. Only `index.php` and static assets are reachable. |
| `app/config/` | `.env` loading and the PDO connection |
| `app/core/` | Router, base controller/model, auth, CSRF, validation, sessions |
| `app/controllers/`, `app/models/`, `app/views/` | Feature code |
| `database/` | `schema.sql`, `seed.sql`, `migrations/` |
| `storage/uploads/items/` | Item photos, **outside** the web root, served through PHP |
| `storage/logs/` | PHP error log |
| `bin/` | CLI commands (`create-admin.php`) |
| `tests/` | Dependency-free test suite |
