# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository overview

Two independent apps in one monorepo, working branch `feature/arab-contractors-union`:

- `arab-contractors-union-api/` — Laravel 12 (PHP 8.2) REST API, MariaDB, Sanctum auth
- `arab-contractors-union-front/` — Vue 3 + TypeScript admin dashboard, built on the Vuexy Vuetify template (Vuetify 3, Pinia, file-based routing via `unplugin-vue-router`)

They are deployed and run independently; there is no shared package/build step between them.

## Commands

### Backend (`arab-contractors-union-api/`)
```bash
composer install
cp .env.example .env && php artisan key:generate && php artisan migrate
php artisan serve                 # http://localhost:8000
php artisan test                  # phpunit — NOTE: no tests/ directory exists yet, this will fail until one is added
```

### Frontend (`arab-contractors-union-front/`)
```bash
npm install
npm run dev                       # http://localhost:5173
npm run build                     # production build
npm run typecheck                 # vue-tsc --noEmit
npm run lint                      # eslint --fix
```
Frontend `.env` needs `VITE_API_BASE_URL` pointing at the backend (`http://localhost:8000/api/v1` locally).

## Deployment

### Mirror repos — this monorepo is not what production pulls

Each subdirectory is mirrored into its own GitHub repo with **unrelated history** (not subtrees, not submodules):

| Subdirectory | Remote | Repo |
|---|---|---|
| `arab-contractors-union-api/` | `pcu-back` | `PcuGaza/PCU-Manager-Backend` |
| `arab-contractors-union-front/` | `pcu-front` | `PcuGaza/PCU-Manager-Frontend` |

A mirror commit's tree is an **exact copy** of the monorepo subdirectory tree. Build one with `git commit-tree <subtree-hash> -p <branch-tip> -m "<msg>"` and push the resulting hash to `refs/heads/<branch>` — never copy files by hand. Verify with `git rev-parse <new-commit>^{tree}` against `git rev-parse <monorepo-commit>:<subdir>`; they must match.

Two branches per mirror, with **different message conventions**:

- `development` — one squashed commit per sync: `sync: mirror <subdir> from monorepo (through <short-hash>)`
- `deploy-new` — replays monorepo commits individually, keeping their **original messages**

`pcu-front` also has `deploy-dist`, holding **pre-built output** (`index.html`, `assets/`, `.htaccess` at root) for a separate static host: `deploy: build from monorepo (frontend <development-hash>)`. It is *not* what the VPS serves.

Pushing only to `development` does not reach production — production pulls `deploy-new`.

### Production VPS (`srv1962001`, `187.77.172.48`)

| | Backend | Frontend |
|---|---|---|
| Path | `/var/www/pcuorg/api` | `/var/www/pcuorg/front` |
| Branch | `deploy-new` | `deploy-new` |
| Serves | `https://api.pcuorg.cloud` (base `/api/v1`) | builds `dist/` locally via `npm run build` |
| Deploy | `./deploy-vps.sh` | `./deploy-vps.sh` |

Both `deploy-vps.sh` scripts back up before touching anything and roll back on failure. The API one aborts if the DB dump is incomplete; the frontend one restores the previous `dist/` if the build fails.

**Do not use [deploy.sh](arab-contractors-union-api/deploy.sh)** — it targets the decommissioned InMotion cPanel host (`~/acu-api`, branch `development`, `ea-php82`).

`GET /` returns 404 by design; smoke-test the API with `GET /api/v1/tenders-public` (public, no auth).

`VITE_API_BASE_URL` in `.env.production` is **baked in at build time** and cannot be changed afterwards — verify it before building.

### Real-time admin notifications (Reverb + queue worker)

Admin-facing notifications (contractor activation, event RSVP, payment submission — see [PaymentSubmittedNotification.php](arab-contractors-union-api/app/Notifications/PaymentSubmittedNotification.php) and friends) broadcast live over Laravel Reverb, on top of the existing DB-backed `notifications` table. This needs **two long-running processes** on the API server, separate from PHP-FPM — `deploy-vps.sh` restarts them (`sudo systemctl restart pcu-api-queue pcu-api-reverb`) but does not create them; set up once per server:

```ini
# /etc/systemd/system/pcu-api-reverb.service
[Unit]
Description=PCU API — Laravel Reverb (real-time notifications)
After=network.target
[Service]
WorkingDirectory=/var/www/pcuorg/api
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
User=www-data
[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/pcu-api-queue.service
[Unit]
Description=PCU API — queue worker (broadcasting notifications)
After=network.target
[Service]
WorkingDirectory=/var/www/pcuorg/api
ExecStart=/usr/bin/php artisan queue:work --tries=3 --backoff=3
Restart=always
User=www-data
[Install]
WantedBy=multi-user.target
```

`sudo systemctl daemon-reload && sudo systemctl enable --now pcu-api-reverb pcu-api-queue`. The API server's `.env` needs `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`/`REVERB_APP_KEY`/`REVERB_APP_SECRET` (generate with `php -r "echo bin2hex(random_bytes(16));"`), `REVERB_HOST=api.pcuorg.cloud`, `REVERB_PORT=443`, `REVERB_SCHEME=https`. The frontend's `.env.production` `VITE_REVERB_APP_KEY` **must match** `REVERB_APP_KEY` exactly.

Reverb binds to `127.0.0.1:8080` only — Nginx fronts it on the same HTTPS domain/cert so the browser connects over `wss://api.pcuorg.cloud/app` with no new firewall port:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

### Uploads under `storage/app/public/`

`.gitignore` excludes `contractors/`, `certificates/`, `receipts/`, `announcements/`, `events/`, `news/`. These once held committed dev placeholders; production has real uploads at the same paths. If a `git pull` ever aborts with *"local changes would be overwritten"* there, back up (`tar -czf ~/backup.tar.gz storage/app/public`) before discarding anything — a bare `git checkout -- storage/` restores placeholders over real files and the pull then deletes them.

## Architecture

### Two API surfaces under one route file

Everything lives in `arab-contractors-union-api/routes/api.php` (~380 lines), split into two unrelated auth realms — check which realm a route/controller belongs to before touching auth logic:

1. **Contractor mobile/portal app** — prefix `contractor/*`, `contractor/auth/*`. Public endpoints (`login`, `verify-identity`, `set-password`, OTP flow) are throttled (`throttle:5,1` / `throttle:10,1`). Protected endpoints require `['auth:sanctum', 'contractor.active']` — the `contractor.active` middleware ([EnsureContractorIsActive.php](arab-contractors-union-api/app/Http/Middleware/EnsureContractorIsActive.php)) blocks inactive/unverified contractors even with a valid token. Login accepts `membership_number` (not email); numbering scheme is legacy `1`–`927`, new members use `{n}_g` starting at `928_g`.
2. **Admin/staff dashboard** — plain `auth:sanctum`, plus `role:admin,accountant` ([CheckRole.php](arab-contractors-union-api/app/Http/Middleware/CheckRole.php)) around financially sensitive routes (dues, exchange rates). Admin CRUD resources mostly live under `dashboard/*` prefixes (e.g. `dashboard/dues`, `dashboard/settings`, `dashboard/legal-files`).

Public, unauthenticated routes also exist for tenders, news, dynamic pages (`pages/{slug}`), and terms — these deliberately omit union-internal fields (see the `tenders-public` vs internal tender comments in the route file).

### Contractor dues ("الذمم") domain

Financial dues/settlements for contractors are a distinct subsystem from `payments`: [ContractorDueController.php](arab-contractors-union-api/app/Http/Controllers/Api/ContractorDueController.php) handles listing, summary, import (from legacy Excel via `ImportLegacyDues` command), manual settlement (`settle`), and admin-initiated payment (`payForContractor`). This is separate from the contractor-facing `payments/transfer` flow (bank transfer notification upload) in [PaymentController.php](arab-contractors-union-api/app/Http/Controllers/Api/PaymentController.php). Renewal eligibility (`contractor/renewal-eligibility`) is gated on unpaid dues — don't conflate due-settlement with membership-payment logic when editing either controller.

### Model layout quirk

Most Eloquent models sit flat in `app/Models/`, but `ContractorDone` lives one level deeper at `app/Models/Models/ContractorDone.php` — check the actual namespace/`use` statement when referencing it rather than assuming the flat path.

### Frontend routing and pages

Routes are generated from the file tree under `resources/ts/pages/` (unplugin-vue-router) — there is no manual route table to edit; adding a `.vue` file there creates the route. Contractor-portal-facing pages live under `pages/contractor/` (`dashboard.vue`, `support.vue`, etc.); everything else under `pages/` (`dues/`, `contractors/`, `tenders/`, `certificates/`, ...) is the internal admin dashboard. Route names are auto-converted from PascalCase to kebab-case in [vite.config.ts](arab-contractors-union-front/vite.config.ts).

The `landing/` page directory is React (`.tsx`), not Vue — [vite.config.ts](arab-contractors-union-front/vite.config.ts) has a dedicated `force-react-compiler` transform that special-cases any file under `/landing/` ending in `.tsx`/`.jsx`. Don't assume the whole frontend is Vue-only when touching landing-page code.

### Legacy artifacts to be aware of

`CONTRACTOR_DASHBOARD_API.postman_collection.json` is tracked as deleted in the working tree and `debug.php` / a new migration are untracked — check `git status` before assuming the repo is in a clean state relative to `main`/previous commits.
