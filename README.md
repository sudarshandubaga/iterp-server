# Iterp API — `server/`

Framework-free **PHP 8.2 + MySQL** REST API for the Iterp application. No Laravel,
no Composer runtime dependencies — just clean, typed, framework-free PHP.

The scaffolding implements a complete **authentication** flow:

- **Model** — `User` (active record over PDO with a small query builder)
- **Controller** — `AuthController` (register / login / logout / me / email verify / password reset)
- **Migration** — a tiny migration runner + schema for `users`, `auth_tokens`, `password_resets`, `email_verifications`
- **Email/Mail** — SMTP mailer + `VerifyEmailMail`, `PasswordResetMail` templates
- **Seeder** — a seeder runner + default `User` records

---

## Directory layout

```
server/
├── bootstrap.php              # env, autoloader, helpers, error handling
├── composer.json              # project metadata + PSR-4 map (Iterp\ => src/)
├── .env / .env.example        # configuration (copy .env.example -> .env)
├── config/                    # app, database, mail config (dot-notation)
├── public/
│   ├── index.php              # HTTP front controller (CORS + dispatch)
│   └── router.php             # router for the built-in PHP dev server
├── routes/api.php             # route table (returns a Router)
├── scripts/
│   ├── migrate.php            # migrate / status / rollback / fresh
│   ├── seed.php               # run seeders
│   └── serve.php              # php -S wrapper
├── src/
│   ├── Core/                  # Router, Database, Model, QueryBuilder, Validator,
│   │                          # Auth, Mailer, Mailable, Migrator, Seeder, ...
│   ├── Controllers/AuthController.php
│   ├── Mail/                  # VerifyEmailMail, PasswordResetMail
│   ├── Database/
│   │   ├── Migrations/        # 0001_create_users, 0002_create_auth_tokens, ...
│   │   └── Seeders/           # DatabaseSeeder, UserSeeder
│   └── Models/User.php
└── resources/views/emails/    # HTML email templates
```

---

## Quick start

### 1. Configure the database

```bash
cp .env.example .env
# edit .env -> set DB_DATABASE, DB_USERNAME, DB_PASSWORD, MAIL_* settings
```

The app connects over **MySQL PDO**. It will also **create the database on demand**
if it doesn't exist yet.

### 2. Run migrations

```bash
php scripts/migrate.php          # apply pending migrations
php scripts/migrate.php status   # show applied / pending
php scripts/migrate.php fresh    # drop-all + re-migrate
php scripts/migrate.php rollback # undo the last batch
```

### 3. Seed defaults

```bash
php scripts/seed.php              # all seeders
php scripts/seed.php UserSeeder   # a single seeder
```

Creates these users (password `password` for all):

| email             | role    |
|-------------------|---------|
| `admin@iterp.test` | admin   |
| `user@iterp.test`  | user    |
| `user2@iterp.test` | user    |

### 4. Run the development server

```bash
php scripts/serve.php               # http://127.0.0.1:8001
php scripts/serve.php 0.0.0.0 9000   # custom host/port
```

or directly:

```bash
php -S localhost:8001 -t public public/router.php
```

Health check: `GET http://127.0.0.1:8001/api/health`

---

## API endpoints

All endpoints return JSON. Authenticated endpoints expect
`Authorization: Bearer <token>`.

| Method | Route                               | Auth | Description                                |
|--------|-------------------------------------|------|--------------------------------------------|
| GET    | `/api/health`                       | no   | Service + MySQL status                     |
| POST   | `/api/auth/register`                | no   | Create account, returns token, sends email |
| POST   | `/api/auth/login`                   | no   | Returns `{ user, token }`                  |
| POST   | `/api/auth/logout`                  | yes  | Revokes the current token                  |
| GET    | `/api/auth/me`                      | yes  | Current user profile                       |
| GET    | `/api/auth/email/verify/{token}`    | no   | Verifies email via emailed link            |
| POST   | `/api/auth/email/resend`            | yes  | Re-sends the verification email            |
| POST   | `/api/auth/password/forgot`         | no   | Emails a one-time reset link               |
| POST   | `/api/auth/password/reset`          | no   | Sets a new password                        |

### Example — register

```bash
curl -X POST http://127.0.0.1:8001/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane","email":"jane@example.com","password":"secret123","password_confirmation":"secret123"}'
```

### Example — login

```bash
curl -X POST http://127.0.0.1:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@iterp.test","password":"password"}'
```

### Example — authenticated request

```bash
curl http://127.0.0.1:8001/api/auth/me -H "Authorization: Bearer <token>"
```

---

## Email / SMTP

Email is sent with a dependency-free SMTP client (`src/Core/Mailer.php`).
Configure in `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@iterp.local
MAIL_FROM_NAME=Iterp
CLIENT_URL=http://localhost:5173   # base URL for password-reset links
```

For local development, point `MAIL_HOST/PORT/USERNAME/PASSWORD` at Mailtrap (or
any SMTP provider). Mail is never blocked in tests: a mail failure only logs an
error and doesn't fail the request.

---

## Notes / conventions

- Schema migrations use numeric prefixes (`0001_`, `0002_`, ...) so the migrator
  always applies them in a deterministic order.
- Passwords are hashed with `password_hash()` (bcrypt) and never stored plaintext.
- Bearer tokens are stored **hashed** (`sha256`) in `auth_tokens`; the plaintext
  is returned once at issue time.
- Email verification and password reset tokens are one-time-use and time-bound.