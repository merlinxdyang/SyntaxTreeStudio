# Syntree PHP Edition

Plain PHP + SQLite syntax tree generator with email registration, optional Google/GitHub OAuth login, and per-user recent tree history.

## Run Locally

```bash
php -S 127.0.0.1:8082 -t syntree
```

Open `http://127.0.0.1:8082/index.php`.

SQLite is created automatically at `data/syntree.sqlite` on first request. The backend seeds one admin account:

- Email: `admin@syntree.local`
- Password: `admin123456`

Change that password from the admin page before using the app outside local development.

## OAuth Setup

Create OAuth apps in Google Cloud Console and GitHub Developer Settings. Configure callback URLs:

```text
http://127.0.0.1:8082/index.php?action=oauth_callback&provider=google
http://127.0.0.1:8082/index.php?action=oauth_callback&provider=github
```

For deployment, replace the host with your real domain. If the app cannot infer the public URL correctly, set `SYNTREE_BASE_URL`.

Set these environment variables before starting PHP:

```bash
export SYNTREE_GOOGLE_CLIENT_ID="..."
export SYNTREE_GOOGLE_CLIENT_SECRET="..."
export SYNTREE_GITHUB_CLIENT_ID="..."
export SYNTREE_GITHUB_CLIENT_SECRET="..."
export SYNTREE_BASE_URL="https://your-domain.example"
```

When a provider is not configured, its login button is disabled.

## Files

- `index.php`: app router, workspace, auth views, admin dashboard
- `admin.php`: admin alias
- `app.js`: browser-side parser, layout, SVG/PNG/LaTeX export, history save
- `style.css`: responsive UI
- `src/db.php`: SQLite schema, seed, record retention
- `src/auth.php`: email registration/login/session handling
- `src/oauth.php`: Google/GitHub OAuth flow

Each user keeps at most 20 saved tree records; older records are deleted after a new save.
