# U3A Port Alfred — Website (w2)

## Directory layout

```
public/      -> deployed to public_html/  (the only web-accessible folder)
app/         -> deployed to app/, a sibling of public_html - NOT web-accessible
sql/         -> deployed to sql/, migration scripts, for reference/manual use
.env         -> NEVER committed. Lives at the FTP account's home directory
                (sibling of public_html and app/), or at the repo root locally.
```

## ⚠️ Before the first deploy: confirm your FTP root

This layout assumes your FTP login drops you into a home directory that
*contains* `public_html/` as a subfolder (true for most cPanel accounts).
If that's the case, `app/` and `.env` can sit outside `public_html`,
where the web server will never serve them directly.

**To check:** log in with an FTP client (FileZilla, or cPanel's File Manager)
and look at the folder you land in. If you see `public_html` as a folder
inside it, you're good — no changes needed.

If your FTP account is instead jailed *directly inside* `public_html` (i.e.
you can't go up a level), tell me and we'll adjust two things:
1. `.github/workflows/deploy.yml` — change `app/`'s and `sql/`'s `server-dir`
   to `./app/` and `./sql/` *inside* `public_html`.
2. `app/config/config.php` and `public/index.php`'s relative paths, since
   `app/` would now be one level shallower.
The `.htaccess` deny rules in `public/.htaccess` already protect `.env`/`.sql`
files if they end up inside `public_html`, as a fallback either way.

## One-time server setup (via File Manager or FTP client)

1. Create the folder structure once: `public_html/` (probably already
   exists), plus `app/`, `sql/`, and `public_html/uploads/` if not already
   present.
2. Copy `.env.example` to `.env`, fill in the real DB credentials and a
   freshly-generated encryption key, and upload it to the home directory
   (next to `public_html`, **not** inside it).
3. Set `public_html/uploads/` permissions to `755` (or `775` if needed)
   so PHP can write uploaded images there.
4. Import `sql/migrations/*.sql` into the database in order, via
   phpMyAdmin (available through cPanel).

After that, all further deploys happen automatically via GitHub Actions —
you should never need to touch the server manually again except for new
migrations.

## GitHub Actions setup

In the repo's Settings → Secrets and variables → Actions, add:
- `FTP_SERVER` — usually `ftp.u3aportalfred.org.za` or an IP from cPanel
- `FTP_USERNAME`
- `FTP_PASSWORD`

Every push to `main` then deploys `public/`, `app/`, and `sql/` to their
respective server locations. `.env` and `public_html/uploads/` are never
touched by deploys (uploads/ is excluded; .env is never in git at all),
so live data and secrets are safe across deploys.

## Local development (Laragon)

1. Clone the repo into `laragon/www/u3a`.
2. Copy `.env.example` to `.env` at the repo root, point `DB_*` at your
   local MySQL (Laragon's default root user, blank password, unless you've
   changed it), set `APP_ENV=local`.
3. Import `sql/migrations/*.sql` into a local database via HeidiSQL/phpMyAdmin
   (Laragon bundles both).
4. Laragon auto-creates a `u3a.test` virtual host pointing at the project
   root — set its document root to the `public/` subfolder (Laragon →
   right-click site → "www" or edit the Apache vhost) so it matches
   production's `public_html` mapping.
5. Visit `http://u3a.test`.

## Status

This is the foundational skeleton only — session/config bootstrap, .env
handling, and the deploy pipeline. See `w2-project-plan.md` for the backlog
of features being built on top of this, one at a time.
