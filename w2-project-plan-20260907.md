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
│   │   ├── auth.php              <- login/role/permission helpers
│   │   ├── presentation_functions.php
│   │   ├── member_functions.php  <- users, roles, payments
│   ├── page/
│   │   ├── public/                (home, recordings, login, register)
│   │   ├── members/                (password change, etc — logged-in, any role)
│   │   └── admin/                  (CRUD: presentations, members, payments)
│   └── templates/
│       ├── header.php
│       └── footer.php
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

**One thing I need from you:** does your cPanel plan give you SSH access and/or
the "Git™ Version Control" feature, or is it File Manager/FTP only? That decides
whether we deploy via `git pull` on the server (clean, fast) or a GitHub Action
that pushes files over FTP/SFTP on every merge to `main` (works anywhere, no
shell needed). Either works with the structure above — just tell me which
access you have and I'll write the actual deploy steps.

## Users & roles — redesign

Current `register.php` requires picking an existing `people_id` — that's a
Settlers Park pattern (register an existing resident) and doesn't fit "anyone
can register." For w2:

- `users` gains `first_name`, `surname`, `given_name`, `email` directly —
  no dependency on the `people` table for U3A members.
- Password hashing moves to `password_hash()` / `password_verify()` (bcrypt).
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
1. Repo skeleton + `.env` handling + deploy pipeline decision
2. `users` table migration (add name/email fields, decouple from `people`)
3. Password hashing migration (bcrypt, transparent upgrade on login)

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
