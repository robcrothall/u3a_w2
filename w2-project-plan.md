# U3A Port Alfred — w2 Rebuild: Structure & Backlog

## Directory structure

Designed for: GitHub as source of truth → deployed to cPanel shared hosting,
with Laragon for local dev on your laptop. Secrets and user-uploaded files are
kept out of git so deployments never overwrite them or leak credentials.

```
u3a-website/                      <- git repo root
├── public/                       <- set this as the domain's document root
│   ├── index.php                 <- front controller / home page
│   ├── .htaccess
│   ├── favicon.ico
│   ├── css/
│   ├── js/
│   ├── img/                      <- static site assets (logo, icons) — committed
│   └── uploads/                  <- GITIGNORED, writable by web server.
│       └── committee/            <- committee photos live here (not in git)
│
├── app/
│   ├── config/
│   │   ├── config.php            <- bootstraps session, error handling, requires below
│   │   └── constants.php         <- NON-secret constants only (site name, addresses...)
│   ├── inc/
│   │   ├── functions.php         <- general helpers (query(), redirect(), test_input()...)
│   │   ├── auth_functions.php    <- register/login/roles/password helpers
│   │   ├── presentation_functions.php
│   │   ├── member_functions.php  <- users, roles, payments
│   └── templates/
│       ├── header.php
│       └── footer.php
│
│   (correction: browser-facing pages - login.php, register.php, recordings.php,
│    admin pages, etc - live in public/, not under app/. Anything inside app/
│    is not web-accessible, so page controllers can't go there.)
│
├── sql/
│   └── migrations/                <- one numbered .sql file per schema change
│       ├── 001_create_presentations.sql
│       ├── 002_create_membership_payments.sql
│       └── ...
│
├── .env.example                   <- committed template (no real secrets)
├── .env                            <- GITIGNORED — real DB creds, encryption key, live here
├── .gitignore
└── README.md
```

**Why this shape:**
- `public/` is the *only* folder the web server serves. Everything in `app/` sits
  outside the web root, so even a server misconfiguration can't expose PHP source
  or config directly (only matters if your cPanel plan lets you set a custom
  document root — see below).
- `.env` holds the DB password, encryption key, mail settings — never committed.
  `config.php` reads it at runtime. This finally gets credentials out of a PHP
  file that lives in git history forever.
- `public/uploads/` is a real folder on the server, gitignored, so a `git pull`
  deploy never touches committee photos or anything else members upload.
- `sql/migrations/` gives us a paper trail of schema changes instead of one
  giant dump — useful once more than one person touches the DB.

## Production hosting (confirmed 2026-10-08)

- Host: cPanel, domain `u3aportalfred.org.za`. **No Git Version Control and no
  SSH** on this account — **FTP / File Manager only**.
- PHP 8.4.26 (native; extensions cannot be changed). Available: `pdo_mysql`,
  `mysqli`, `mbstring`, `openssl`, `curl`, Argon2 for `password_hash()`.
  **Not available:** `sodium` — use `openssl` for any encryption.
- Database: MariaDB 11.4, server default charset is latin1 → every w2 database,
  table and connection must be explicitly **utf8mb4**.
- Use PDO with prepared statements (no emulated prepares). Retire the SQL Server
  (`sqlsrv`) example config from w1.
- Local dev (Laragon) runs PHP 8.4.3, so it matches production.
- HTTPS is forced by cPanel ("Force HTTPS Redirect" is on).
- `w1/index_live.php` is the live home page (uploaded manually as `index.php`);
  its production `.htaccess` is kept in `w1/deploy/htaccess.production`.

**Deployment (decided 2026-10-08):** GitHub Action over FTPS
(`.github/workflows/deploy.yml`, credentials in GitHub secrets) on every push to
`main`. It must **never touch** `.env` or `public/uploads/`.

**Home page rule:** the pretty public home page (`public/index.php`, plus
`public/css|js|img|docs|favicon.ico` and `public/.htaccess`) is part of this
repo, so every deploy keeps it. It may change (menu, content) but is never
replaced by placeholder/dummy code; changes are previewed on the test subdomain
before `main`. Because a push to `main` deploys straight to `public_html/`, do
work on a branch and merge to `main` only when ready.

**cPanel mapping of the tree above:** `public/` → `public_html/` (or the test
subdomain's folder); everything else (`app/`, `sql/`, `.env`) → a private folder
outside the web root, in the FTP home directory (`~/app/`, `~/sql/`, `~/.env`), as set up in `deploy.yml`. `index.php` locates
`app/` with a relative path to that folder.

**Test first:** create a subdomain such as `w2.u3aportalfred.org.za` with its
own document root and its own database, so w2 can be tested on the real server
without touching the live home page. Only at go-live is the main domain switched.


## Users & roles — redesign

Current `register.php` requires picking an existing `people_id` — that's a
Settlers Park pattern (register an existing resident) and doesn't fit "anyone
can register." For w2:

- `users` gains `first_name`, `surname`, `given_name`, `email` directly —
  no dependency on the `people` table for U3A members.
- Password hashing moves to `password_hash($pw, PASSWORD_DEFAULT)` / `password_verify()` (bcrypt today; `password_needs_rehash()` handles upgrades; Argon2 also available on the host).
  The current `crypt()` calls use PHP's default DES-based hashing (very weak,
  effectively only the first 8 characters matter). We can migrate gradually:
  on successful login, if a user's hash is still old-format, transparently
  re-hash it with bcrypt and save it — no forced reset needed for anyone who
  logs in normally.
- Roles map onto the `roles` / `user_roles` tables already in your schema:
  `registered`, `paid_up`, `admin`. A user can hold more than one (e.g.
  paid-up *and* admin).

## Membership & payments (new)

- `membership_payments`: `id`, `user_id`, `year`, `amount`, `paid_date`,
  `recorded_by` (admin's user id). One row per member per year.
- "Paid-up" becomes a computed status (does a payments row exist for the
  current year?), not a manually-maintained flag — so it can't drift out of
  sync.
- Door list = admins print paid-up members for the current year.
- Mailmerge export = CSV of all registered members' name + email (Excel/Word
  mail-merge reads CSV directly).

## Backlog (not building all of this now — one bite at a time)

**Foundational (do first, unlocks everything else)**
1. ~~Repo skeleton + `.env` handling + deploy pipeline decision~~ ✅ done
2. ~~`users` table + roles + password hashing (bcrypt, register/login/change/admin-reset)~~ ✅ done
3. Membership data cleanup (Excel + CSV comparison) — in progress
4. Import cleaned member data into `users` (temporary passwords, forced change)

**Content**
4. Homepage: about text + committee photo (with upload facility) — editable
5. Upcoming/past events (shares the `presentations` table with Recordings)
6. Recordings admin (already designed in w1 — port across)

**Membership**
7. Self-service registration (no admin approval, per your instructions)
8. Roles: registered / paid-up / admin
9. Record annual payment (admin action)
10. Paid-up door list (printable)
11. Full member CSV export for mailmerge
12. Logged-in user: change own password
13. Admin: reset a user's password, force change on next login

**Ideas worth considering later (not committed yet)**
- Newsletter sign-up / send log (you mentioned registered members get newsletters)
- Simple event RSVP / attendance count for catering numbers
- Speaker/volunteer register (register.php already collects "willing to speak",
  "skills", "subjects wanted" — currently captured nowhere reusable)
- Renewal reminder email a month before a member's paid-up year lapses
- Committee-only notes/minutes page

Nothing above is built yet — just recorded so it isn't lost.
