# CampusFind

**CampusFind** is a campus lost-and-found web portal. Students post items they have lost or found, search what others have reported, verify ownership through claims, chat privately and get items back safely. Admins moderate users, posts and reports.

Built with plain PHP, MySQL and Bootstrap as a BCA Semester 1 project: no frameworks, easy to read, secure by default.

## Objectives

- Replace notice boards and group chats with one searchable place for lost and found items.
- Return items to their real owners through verification (claims and secret details).
- Keep students safe: private messaging, reporting and admin moderation.
- Demonstrate secure web development fundamentals with simple, readable code.

## Features

| Module | What it does |
|---|---|
| **Authentication** | Register, log in/out, forgot and reset password, login rate limiting, admin CLI command |
| **Items** | Post lost/found items with up to 3 photos, browse, search, filter, edit, delete, mark as returned or closed |
| **Claims** | Claim a found item with an identifying detail; the finder approves or rejects |
| **Messaging** | Private one-to-one chat about an item, unread badges, read receipts |
| **Notifications** | Navbar bell and notification page for claim and moderation events |
| **Possible Matches** | Rule-based score (category, location, words, date) suggesting similar lost/found items |
| **Reports** | Report a post or a user; track the status of your reports |
| **Admin Panel** | Statistics and charts, user activation, post moderation, report review and resolution |

## Technologies

- **Backend:** PHP 8.1+ (no framework), PDO
- **Database:** MySQL 8 (InnoDB, utf8mb4)
- **Frontend:** HTML, CSS, vanilla JavaScript, Bootstrap 5.3, Bootstrap Icons, Inter font (loaded from a CDN)
- **Image processing:** PHP GD extension
- **Testing:** custom dependency-free test runner (`php tests/run.php`)

## Folder structure

```
CampusFind/
├── public/                # Web root (the only folder the browser can reach)
│   ├── index.php          # Front controller: every request starts here
│   └── assets/            # css/app.css (design system), js/app.js
├── app/
│   ├── bootstrap.php      # Loads config, helpers, autoloader, session
│   ├── routes.php         # All URLs and their access rules
│   ├── config/            # .env loader and PDO connection
│   ├── core/              # Router, Auth, Session, Csrf, Validator, ImageUpload, ...
│   ├── controllers/       # One controller per module
│   ├── models/            # Database queries (User, Item, Claim, Message, ...)
│   ├── helpers/           # Small global functions (escaping, badges, dates)
│   └── views/             # PHP templates (layouts, partials, pages)
├── bin/create-admin.php   # Command to create the first admin
├── database/
│   ├── schema.sql         # Full database structure
│   ├── seed.sql           # Categories and campus locations
│   └── migrations/        # Changes for databases created before an update
├── storage/               # Not web-accessible
│   ├── uploads/items/     # Item photos (served through PHP)
│   ├── sessions/          # PHP session files
│   └── logs/              # Error log and local mail log
├── tests/                 # Automated tests
└── .env.example           # Configuration template
```

## Installation (macOS)

### 1. Install PHP and MySQL

```sh
brew install php mysql
brew services start mysql
```

Check that the required PHP extensions are present: `php -m | grep -E 'pdo_mysql|gd|fileinfo|mbstring'`.

### 2. Get the code

```sh
git clone https://github.com/PrateekSharma8800/Campus_Lost_and_Find.git CampusFind
cd CampusFind
```

### 3. Create the database and a database user

```sh
mysql -u root
```

```sql
CREATE DATABASE campusfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'campusfind'@'localhost' IDENTIFIED BY 'choose-a-password';
CREATE USER 'campusfind'@'127.0.0.1' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON campusfind.*      TO 'campusfind'@'localhost', 'campusfind'@'127.0.0.1';
GRANT ALL PRIVILEGES ON campusfind_test.* TO 'campusfind'@'localhost', 'campusfind'@'127.0.0.1';
EXIT;
```

The second grant lets the test suite create its own separate `campusfind_test` database.

### 4. Configure the app

```sh
cp .env.example .env
```

Open `.env` and set `DB_PASS` to the password you chose. The other defaults work for local development:

| Key | Meaning |
|---|---|
| `APP_URL` | Base URL used in emails (`http://localhost:8000`) |
| `APP_DEBUG` | `true` shows error details locally; use `false` in production |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection |
| `DB_TEST_NAME` | Database used only by tests (must differ from `DB_NAME`) |

`.env` is listed in `.gitignore` and must never be committed.

### 5. Load the schema and seed data

```sh
mysql -u campusfind -p campusfind < database/schema.sql
mysql -u campusfind -p campusfind < database/seed.sql
```

#### Migrations

`schema.sql` always contains the **latest** structure, so a fresh install needs no migrations. Only if your database was created before an update, run the missing files in `database/migrations/` in number order:

```sh
mysql -u campusfind -p campusfind < database/migrations/001_add_password_changed_at.sql
mysql -u campusfind -p campusfind < database/migrations/002_extend_reports.sql
```

### 6. Create the first admin account

```sh
php bin/create-admin.php
```

Enter an email, a name and a password (at least 8 characters with a letter and a number). The password is typed at a hidden prompt. If the email already belongs to a student, the command offers to promote that account to admin.

## Running the project

```sh
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000>. Admins see an **Admin** link in the navbar (or go to `/admin`).

**Password reset emails:** there is no mail server locally, so reset emails are written to `storage/logs/mail.log`. Open the link from that file.

## Running the tests

```sh
php tests/run.php
```

The suite (146 tests) rebuilds the separate `campusfind_test` database on every run and starts its own PHP server on a random port, so your development data is never touched. It covers the schema, validation, security rules and every module end to end.

## Security features

- **Passwords:** hashed with `password_hash()` (bcrypt) and checked with `password_verify()`; minimum 8 characters with a letter and a number.
- **SQL injection:** every query uses PDO prepared statements; stacked queries are disabled.
- **XSS:** all output is escaped with `e()`; a strict Content-Security-Policy blocks inline scripts.
- **CSRF:** every form includes a token, checked for every POST request by the router.
- **Sessions:** HttpOnly and SameSite cookies, strict mode, new session ID on login, 2-hour idle and 12-hour absolute timeout, all sessions ended after a password reset or deactivation.
- **Login protection:** lockout after 5 failed attempts per email or 20 per IP within 15 minutes; identical errors for unknown emails.
- **Password reset:** single-use tokens valid for 60 minutes, stored only as SHA-256 hashes.
- **Uploads:** size and real file type checked, images re-encoded with GD (removes hidden code and GPS data), random file names, stored outside the web root.
- **Access control:** owners-only editing, admin-only panel, private conversations, no self-reporting, open-redirect protection.
- **HTTP headers:** CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy and no caching of private pages.

## Screenshots

> Screenshots will be added here.

| Page | Screenshot |
|---|---|
| Home | _coming soon_ |
| Browse items | _coming soon_ |
| Item details and possible matches | _coming soon_ |
| Post an item | _coming soon_ |
| Messages | _coming soon_ |
| Admin overview | _coming soon_ |

## Future improvements

- Send real emails through SMTP (password reset, notifications).
- Restrict sign-up to a college email domain and add email verification.
- Live chat updates without reloading the page.
- Show times in each user's local time zone everywhere.
- Upload screenshots and deploy to a hosted server with HTTPS.
- Let admins manage categories and locations from the panel.
