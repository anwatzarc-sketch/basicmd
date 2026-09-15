# Aster Medical Center

Public website and administration portal for Aster Medical Center, Bole Sub-city, Addis Ababa.

Bilingual (English / አማርኛ), PHP 8.3, MySQL 8.0, Tailwind CSS 3. No framework.

---

## What this is

A production rebuild of two prototypes (`prototype/aster_medical_center_website.html` and
`prototype/aster_medical_center_admin_portal.php`), which are kept in `prototype/` for
reference. The visual design is carried over intact; everything behind it is new.

**Four things earn money here:**

| | |
|---|---|
| **Health packages** | Prepaid screening products (Essential Check ETB 2,500 / Executive Health ETB 6,500) with a configurable deposit to hold the slot. |
| **Express queue** | A +20% surcharge to be seen first within a time block. Rate is editable in Settings without a deploy. |
| **Email retention** | Booking confirmation, a reminder 24h before the visit, and a post-visit follow-up. This is what moves the no-show rate. |
| **Knowledge Hub** | SEO articles emitting `MedicalWebPage` / `MedicalCondition` JSON-LD with clinician review metadata, to win high-intent local search. |

### Payments are manual by design

There is **no payment gateway integration**. The patient is shown transfer instructions
(CBE, Telebirr, Dashen, or pay-at-reception), transfers the money themselves, and uploads
a photo of the slip. A Finance officer verifies it in the admin portal. That mirrors how
clinic payments in Addis Ababa actually clear, and it means there are no merchant
credentials to provision before going live.

Two consequences worth knowing:

- An appointment's `amount_paid` is **derived** from verified payment rows, never
  incremented. A rejected-then-corrected slip cannot leave the total wrong.
- Every uploaded receipt is SHA-256 hashed. A hash seen on another booking is surfaced to
  Finance *before* the approve button — reusing one receipt across two bookings is the
  obvious attack on a manual flow.

---

## Requirements

| | |
|---|---|
| PHP | **8.3+** (uses typed class constants and `json_validate()`) |
| Extensions | `pdo_mysql`, `mbstring`, `gd` (with WebP), `fileinfo`, `openssl` |
| Database | MySQL **8.0.16+** |
| Node | 18+ — build only, not needed on the server |
| SMTP | Any host, or a local sink (MailHog) for development |

`intl` is **not** required. The Ethiopian calendar and ETB formatting are implemented
natively, because `intl` is frequently absent on Ethiopian shared hosting.

> **MariaDB is not supported as-is.** The schema uses `utf8mb4_0900_ai_ci`, which is
> MySQL-only. On MariaDB, replace it with `utf8mb4_unicode_ci` throughout `schema.sql`
> and re-check the `INET6_NTOA` usage in the audit viewer.

---

## Install

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build          # compiles CSS + downloads self-hosted fonts

cp .env.example .env                  # then edit DB_*, MAIL_*, APP_URL, TRUSTED_HOSTS

mysql -u USER -p DATABASE < database/schema.sql
mysql -u USER -p DATABASE < database/seed.sql

php bin/install.php                   # checks everything, creates the first admin
```

`bin/install.php` verifies the PHP version and extensions, generates `APP_KEY`, confirms
storage is writable, checks all 16 tables exist, and refuses to continue if
`UPLOAD_PROOF_DIR` is inside `public/`. It then prompts for the first SuperAdmin
interactively.

No user is seeded. Shipping a known password hash is how demo installations get owned.

### Web server

Point the document root at **`public/`**, not the project root. Everything else — `src/`,
`storage/`, `lang/`, `.env`, `vendor/` — sits above it and is unreachable over HTTP no
matter what else is misconfigured.

- nginx **with TLS**: `deploy/nginx.conf`
- nginx **plain HTTP**: `deploy/nginx-http-only.conf`
- Apache / cPanel: `public/.htaccess` (already in place; the HTTPS redirect is
  commented out — uncomment it when TLS goes live)

### Changing the domain

`APP_URL` alone is not enough. These must agree, or the site breaks in ways that look
like unrelated bugs:

| Setting | Consequence if stale |
|---|---|
| `APP_URL` | wrong canonical/sitemap/email links |
| **`TRUSTED_HOSTS`** | **every request returns `400 Invalid host` — the site is entirely dead** |
| `SESSION_SECURE` | must match the scheme in `APP_URL` (see below) |
| `MAIL_FROM_ADDRESS` / `MAIL_REPLY_TO` | SPF/DKIM failure → confirmations land in spam |
| `MAIL_ADMIN_INBOX` / `MAIL_FINANCE_INBOX` | nobody sees new bookings or payment receipts |
| `deploy/nginx*.conf` `server_name` | nginx serves the wrong vhost or none |
| `system_settings.email_public` | the wrong address is shown to patients (editable in Settings) |

### HTTP vs HTTPS — these two must always agree

`SESSION_SECURE=true` marks the session cookie `Secure`, so the browser only sends it over
HTTPS. Combined with `APP_URL=http://`, sign-in *appears* to succeed and then every
following request is anonymous and bounces back to the login page. It reads as broken
authentication; it is this one flag.

- Plain HTTP → `SESSION_SECURE=false`, use `nginx-http-only.conf`, leave the `.htaccess`
  redirect commented out.
- TLS → `SESSION_SECURE=true`, use `nginx.conf`, uncomment the `.htaccess` redirect.

The app omits `upgrade-insecure-requests` and HSTS automatically when the connection is
not secure, so neither of those will break a plain-HTTP deployment.

**Get a certificate.** This site carries patient names, phone numbers, typed symptoms,
bank receipts and staff passwords. Over plain HTTP all of it is readable by anyone on the
network path. `certbot --nginx -d medical.pyramid.biz.et` takes about a minute.

### Cron

```cron
*/5 * * * *   php /path/to/bin/queue-worker.php --quiet     # send queued email
0   * * * *   php /path/to/bin/send-reminders.php --quiet   # reminders, follow-ups, no-shows
```

Both take a lock, so an overrun never produces duplicate emails to a patient.
Without these two lines, **no email is ever sent** — requests only enqueue.

---

## Deploying to Plesk

Build the bundle locally — the server needs no Node and no Composer:

```bash
npm run build          # compile CSS, fetch fonts
php bin/package.php --zip
```

That produces `build/deploy/` (~2 MB, 257 files) and `build/deploy.zip` (~0.8 MB),
containing only what runs. `node_modules/`, the Tailwind source and config, the font
downloader, the prototypes and the tests are all left behind. `.env` is deliberately
**not** included — it holds secrets and would clobber the live one on a redeploy.

### What ships

| Path | Why |
|---|---|
| `public/` | **The document root.** Front controller, `.htaccess`, compiled CSS/JS/fonts |
| `src/` | Application code |
| `resources/views/` | Templates (`resources/css/` is build-source, excluded) |
| `lang/` | English + Amharic dictionaries |
| `bin/` | Installer, queue worker, reminder scheduler |
| `vendor/` | Autoloader + PHPMailer — shipped so the server needs no Composer |
| `database/` | `schema.sql`, `seed.sql` — used once, at install |
| `storage/` | Created **empty** and writable. Receipts and logs live here |
| `composer.json` / `.lock` | Only if you ever regenerate the autoloader on the server |
| `.env.example` | Template to copy to `.env` on the server |

### Steps

1. **Upload and unzip** into the domain directory, e.g.
   `/var/www/vhosts/medical.pyramid.biz.et/aster/`.

2. **Set the document root** — this is the step people get wrong.
   *Websites & Domains → Hosting Settings → Document root* must point at
   `aster/public`, **not** at `aster/` and not at `httpdocs`.

   Everything else — `src/`, `storage/`, `.env`, `vendor/` — then sits *above* the
   document root and is unreachable over HTTP no matter how the vhost is configured.
   If you leave the document root at the project folder, `.env` and every payment
   receipt become downloadable.

3. **PHP 8.3** — *Hosting Settings → PHP support*. Confirm `pdo_mysql`, `mbstring`,
   `gd`, `fileinfo` and `openssl` are enabled, and set `upload_max_filesize` ≥ 12M and
   `post_max_size` ≥ 14M (a phone photo of a bank slip is easily 4–6 MB).

4. **Database** — create it in *Databases*, then import `database/schema.sql` followed by
   `database/seed.sql` via phpMyAdmin or the Plesk import tool.

5. **Environment** — `cp .env.example .env`, then set `DB_*`, `APP_URL`,
   **`TRUSTED_HOSTS`**, `SESSION_SECURE` and the `MAIL_*` values.

6. **Permissions** — `storage/` must be writable by the web user:
   ```bash
   chmod -R 775 storage
   chown -R <subscription-user>:psacln storage
   ```

7. **Install** — `php bin/install.php` (Plesk *SSH Terminal*, or run it once via a
   scheduled task). It verifies everything and creates the first SuperAdmin.

8. **Scheduled Tasks** — *Websites & Domains → Scheduled Tasks*, two entries of type
   "Run a PHP script":

   | Script | Schedule |
   |---|---|
   | `bin/queue-worker.php` with argument `--quiet` | every 5 minutes |
   | `bin/send-reminders.php` with argument `--quiet` | hourly |

   Without these, **no email is ever sent**.

9. **TLS** — *SSL/TLS Certificates → Let's Encrypt*. Then set `SESSION_SECURE=true` and
   enable Plesk's "Permanent SEO-safe 301 redirect from HTTP to HTTPS".

### Notes

- Plesk manages its own nginx/Apache config, so `deploy/nginx*.conf` is **reference only**
  there. The shipped `public/.htaccess` handles rewriting on Plesk's Apache.
- Redeploying: replace `public/`, `src/`, `resources/`, `lang/`, `bin/`, `vendor/`.
  Never overwrite `.env` or `storage/`.
- Back up `storage/uploads/proofs/` — those are financial records and are not
  reproducible.

---

## Architecture

```
public/index.php          Front controller: the only web-reachable PHP file
src/
  Domain/                 Enums, entities, value objects, exceptions — no I/O
  Application/            Services (booking, payment, auth, notifications) + DTOs
  Infrastructure/         PDO, security, mail, storage, container
  Presentation/           Router, controllers, middleware, view
resources/views/          Plain-PHP templates
lang/{en,am}.php          348 translation keys each, kept in sync by a checker
database/                 schema.sql + seed.sql
bin/                      install, queue worker, reminders, checkers
```

### How overbooking is prevented

This is the part most worth understanding, because a naive implementation loses the race.

1. `SELECT ... FOR UPDATE` on the doctor row serialises every booking for that doctor.
   Requests for *different* doctors never contend.
2. The capacity count runs **inside** that lock, so the number read is still true at the
   moment of insert.
3. A unique index on `(doctor_id, date, time_slot, patient_phone)` is the backstop — it
   also makes a double-clicked submit button harmless.

Verified under load: 12 concurrent processes against a 4-seat slot produce exactly
4 bookings and 8 clean refusals.

### Roles

| Role | Can | Cannot |
|---|---|---|
| **SuperAdmin** | everything | — |
| **Receptionist** | bookings, enquiries, read payments | **verify payments**, users, settings |
| **Finance** | verify payments, revenue reporting | create bookings, CMS, users |
| **Doctor** | own queue only, clinical notes, draft articles | payments, other doctors' patients |

Receptionist and Finance are deliberately separated: whoever books a slot must not also be
able to mark it paid. A Doctor's scoping is enforced in the repository *and* re-checked on
the detail route, so typing another appointment's id into the URL returns 403.

---

## Security

| | |
|---|---|
| SQL injection | 100% bound parameters. `bin/check-sql.php` proves no statement reuses a named placeholder (which would fail under non-emulated prepares). |
| XSS | Everything escapes through `View::e()`. Stored CMS HTML is filtered by an allow-list sanitiser **on save and on render**. |
| CSP | Per-request nonce, no `unsafe-inline` for scripts. No inline event handlers anywhere. |
| CSRF | Synchroniser token on every non-GET route, public forms included. |
| Passwords | Argon2id (64 MB / t=4 / p=2), rehashed transparently when cost rises. |
| Sessions | Rotated on login, idle **and** absolute timeouts, HttpOnly + Secure + SameSite, coarse client fingerprint. |
| Brute force | Per-IP rate limit **and** per-account lockout, plus constant-ish response time so staff emails cannot be enumerated. |
| Uploads | Type from magic bytes, images re-encoded through GD (strips payloads and EXIF/GPS), random stored names. |
| Payment proofs | Stored **outside the webroot**, streamed only through an authenticated route that writes an audit entry per view. |
| Audit | Append-only trail of who did what, with before/after diffs. Passwords and clinical notes are redacted. |

Error pages never show a stack trace; debug output is force-disabled whenever
`APP_ENV=production`, regardless of `APP_DEBUG`.

---

## Localisation

Both languages are complete — 348 keys each, verified in sync:

```bash
php bin/check-translations.php     # fails on drift, blanks, or placeholder mismatch
```

Dates render in the Ethiopian calendar for Amharic users with the Gregorian date in
parentheses, because every bank slip and lab report they will be handed is Gregorian.
The conversion is Julian-Day-Number based and round-trips losslessly (verified across
4,000 consecutive days).

Amharic content is optional per record. A doctor or article without a translation falls
back to English and is excluded from the Amharic `hreflang` alternate rather than
publishing an empty page.

---

## Development

```bash
npm run dev                                   # Tailwind watch
php -S 127.0.0.1:8000 -t public public/index.php

php bin/queue-worker.php                      # drain the mail queue by hand
php bin/send-reminders.php --dry-run          # see what reminders would go out
```

Set `MAIL_MAILER=log` to write rendered emails to `storage/logs/mail.log` instead of
sending, or point SMTP at MailHog on `127.0.0.1:1025`.

Set `ASSETS_BUILT=false` to load Tailwind from the CDN and skip `npm run build` while
iterating. Production ignores this flag.

### Checks

```bash
php bin/check-sql.php            # repeated SQL placeholders
php bin/check-translations.php   # EN/AM dictionary drift
```

---

## Operations

**Where things are**

- Application logs: `storage/logs/app-YYYY-MM-DD.log` (30-day rotation)
- Mail logs: `storage/logs/mail-*.log`
- Payment receipts: `storage/uploads/proofs/<booking-ref>/` — **back these up**; they are
  financial records and are not reproducible.

**Email stopped going out?** Check `/admin/settings/mail`. If `queued` is climbing, the
cron worker is not running. `failed` rows can be retried from that page.

**Changing prices** Service and package prices, the express surcharge rate, booking lead
time and horizon are all editable in the admin portal. No deploy needed.

**Before going live**

- [ ] Replace the placeholder account numbers in `/admin/payments/methods` with the real ones
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `TRUSTED_HOSTS=medical.pyramid.biz.et` — **without this every request 400s**
- [ ] `SESSION_SECURE` matches the scheme in `APP_URL`
- [ ] Real SMTP credentials, and a `MAIL_FROM_ADDRESS` your relay is authorised to send for
- [ ] Point `MAIL_ADMIN_INBOX` / `MAIL_FINANCE_INBOX` at mailboxes someone reads daily
- [ ] Clear `MAIL_CATCH_ALL`
- [ ] Confirm both cron entries are installed (no email is sent without them)
- [ ] Get a TLS certificate, then switch to `nginx.conf` and `SESSION_SECURE=true`
- [ ] Have the clinic's legal adviser review `/privacy` and `/terms`
- [ ] Change `APP_ADMIN_PATH` from `admin` if you want the portal off a guessable path
- [ ] Delete any test accounts: `DELETE FROM users;` then `php bin/install.php`
