# Deploying NightLifeBD: GitHub → Vercel + TiDB Cloud

```
GitHub (main) ──push──▶ Vercel (PHP 8.4 serverless, api/index.php)
      │                        │
      │ Actions                ├──▶ TiDB Cloud   (users, sessions, cache, reports…)
      ├─ migrate.yml ─────────▶│──▶ S3/R2 bucket (incident photos)
      ├─ scheduler.yml ───────▶│    /internal/cron/{frequent|hourly|daily}
      └─ build-assets.yml      └──▶ Gemini / ORS / Telegram (optional)
```

## 1. Create the TiDB Cloud database
1. In TiDB Cloud create a cluster (choose **Singapore / ap-southeast-1**, closest to Bangladesh).
2. Click **Connect** → choose **General**/`Laravel`-style credentials and copy host, port (4000), username (it has a prefix like `xxxx.root`) and password.
3. Create the database (SQL Editor): `CREATE DATABASE nightlifebd;`
4. Connections must use TLS. This repo already ships a CA bundle (`certs/cacert.pem`); you only need `DB_SSL=true`.

## 2. Push to GitHub
```bash
git init && git add -A && git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/<you>/nightlifebd.git
git push -u origin main
```
`public/build` (compiled CSS/JS) **is committed on purpose**: the Vercel PHP runtime doesn't run Vite.

## 3. Photo storage (required for photo uploads)
Vercel's disk is ephemeral. Create a bucket (Cloudflare R2 has a free tier) and add the keys (see `.env.vercel.example`).
Then install the S3 driver **once, locally**, and commit the updated lockfile:
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
git add composer.json composer.lock && git commit -m "Add S3 driver" && git push
```
(If you skip photos, set `REPORT_PHOTOS_DRIVER=local`; uploads will then not persist.)

## 4. Import into Vercel
1. Vercel → **Add New → Project** → import the GitHub repo. Framework preset: **Other**. Leave build/output settings empty (`vercel.json` handles it).
2. Settings → **Functions** → set region to **Singapore (sin1)** to sit next to TiDB.
3. Add every variable from `.env.vercel.example` (Production + Preview).
4. Deploy. From now on every push to `main` deploys automatically; pull requests get preview URLs.

## 5. GitHub secrets (Settings → Secrets and variables → Actions)
| Secret | Value |
|---|---|
| `APP_KEY` | same as Vercel |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | TiDB credentials |
| `APP_URL` | `https://your-project.vercel.app` |
| `CRON_SECRET` | same as Vercel |

## 6. Create tables and first admin
1. Actions → **Migrate TiDB database** → *Run workflow* → tick **seed** (creates roles + safe-haven map points).
2. Register on the live site, then promote yourself. Locally, with the production `DB_*` values in a temporary `.env`:
   ```bash
   php artisan user:promote you@example.com super_admin
   ```
> ⚠️ Do **not** run `php artisan db:seed` (the default seeder) on production: `RoleSeeder` creates `admin@test.com` with password `password`.

## 7. Scheduled jobs
Safety timers and stale-trip checks must run frequently. Options:
- **GitHub Actions** (`scheduler.yml`, included): every 5 min, but GitHub may delay runs.
- **cron-job.org** (free, 1-minute): create 3 jobs hitting `GET https://<app>/internal/cron/frequent` (every minute), `/hourly`, `/daily` with header `Authorization: Bearer <CRON_SECRET>`.
- **Vercel Cron** (Pro plan only for per-minute): add to `vercel.json`
  `"crons": [{ "path": "/internal/cron/frequent", "schedule": "* * * * *" }]` — Vercel sends `Authorization: Bearer $CRON_SECRET` automatically. Hobby plans reject cron expressions that run more than once a day, so don't add this on Hobby.

## Known limits on serverless
- **No GD extension** in the Vercel PHP runtime: photo thumbnails use the app's built-in fallback (EXIF stripped, no resize).
- **Queue**: keep `NIGHTLIFE_USE_QUEUE=false` / `QUEUE_CONNECTION=sync`. AI analysis runs at the end of the request, so report submission may take a few seconds longer.
- **Function timeout** is 30 s (`vercel.json`).
- Demo mode (`NIGHTLIFE_DEMO_ENABLED`) should stay `false` in production.
- Local-only helpers (`*.bat`, `storage/` writes) are excluded from the deployment by `.vercelignore`.
