# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⛔ ALWAYS ASK BEFORE ANY PUSH: staging or production?

**Never run `git push` in this project — or in the PcuGaza repos — without first asking the user, explicitly, which environment it is meant to reach, and waiting for an answer.** Ask even when it looks obvious, even when the user already said "push", and even when you asked earlier in the same session. This is a standing instruction added by the user on 2026-09-23 and it overrides any inference you would otherwise make from the branch name.

Why it matters: a push is not a local action here — it *is* a deployment trigger, and the two environments have different blast radii.

| You push to | Reaches | Effect |
|---|---|---|
| `admin_dshbaord_pfi` @ `feature/arab-contractors-union` | **STAGING** | deploys automatically within ~2 min, runs `migrate --force` on `pcuorg` |
| `PcuGaza/*` @ `main` | **PRODUCTION** | does *not* auto-deploy, but stages the code that the next manual run ships to real users |

Phrase the question concretely — name the repo, the branch, and what will actually happen — rather than asking "staging or production?" in the abstract. If the answer is production, restate what the user is about to affect (real contractor data, real FCM pushes to real phones) and get a second confirmation before running the deploy workflow.

See [DEPLOYMENT.md](DEPLOYMENT.md) for the full picture.

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

> **Full practical guide: [DEPLOYMENT.md](DEPLOYMENT.md)** — rollback, env vars, DNS/SSL, isolation proofs, and the "never do this on production" list. The summary below is orientation only.

### Pushing to `feature/arab-contractors-union` deploys to STAGING, not production

As of 2026-09-23 the environments are split. **This monorepo deploys only to staging**: `staging.pcuorg.cloud` + `api.pcuorg.cloud`, DB `pcuorg`, `/var/www/pcuorg/{api,front}`, Reverb `:8080`, and **Firebase deliberately disabled** (`FIREBASE_CREDENTIALS=` empty → `LogPushSender`) so test pushes can never reach real contractor phones.

**Production is a different pair of repos**, revived for exactly this purpose and no longer stale: `PcuGaza/PCU-Manager-Backend` and `PcuGaza/PCU-Manager-Frontend`, branch **`main`** → `pcuorg.cloud` / `production.pcuorg.cloud` / `api-production.pcuorg.cloud`, DB `pcuorg_production`, `/var/www/pcuorg/production/{api,front}`, Reverb `:8081`. Their older branches (`development`, `deploy-new`, `deploy-dist`) are untouched and still retired — only `main` matters now.

**Production deploys are `workflow_dispatch` only — pushing to `main` deploys nothing.** This is deliberate and must not be "fixed" by adding `on: push` back: required-reviewer rules and branch protection both need a paid plan on private repos, and PcuGaza is on the free plan, so `on: push` + `environment: production` produces an *unprotected* auto-deploy that merely looks gated. (Three such runs fired during setup and only failed harmlessly because the secrets didn't exist yet.) If the org is ever upgraded to Pro/Team, restore `on: push` *and* add the required-reviewer rule together.

The PcuGaza repos **share no git history with this monorepo** (`git merge-base` is empty), so syncing code to production is a tree copy via `commit-tree`, never a merge — see DEPLOYMENT.md §6, including the two files that are *deliberately* divergent there (`deploy-vps.sh`, `.github/workflows/deploy-production.yml`).

Staging pipeline, on every push to `feature/arab-contractors-union` that touches `arab-contractors-union-api/**` or `arab-contractors-union-front/**`:

1. GitHub Actions (`appleboy/ssh-action`) SSHes into the VPS using the `SSH_HOST`/`SSH_USERNAME`/`SSH_PRIVATE_KEY` repo secrets.
2. It runs `cd /var/www/pcuorg/api && ./deploy-vps.sh`, then `cd /var/www/pcuorg/front && ./deploy-vps.sh`.
3. Each `deploy-vps.sh` does **not** `git pull` inside the app directory. It `git fetch`+`reset --hard origin/feature/arab-contractors-union` a **separate, clean monorepo clone** at `/var/www/pcuorg/monorepo`, then `rsync -a --delete` the matching subdirectory (e.g. `monorepo/arab-contractors-union-api/`) into place (`.env`/`.env.production`, `node_modules`, `dist`, `.git` excluded from the rsync).
4. Frontend build happens on the server after the rsync: `npm install` + `npm run build`, verified by checking `dist/index.html` exists and the built bundle has the right API base URL baked in.

**Consequence — `/var/www/pcuorg/api` and `/var/www/pcuorg/front` are themselves git repos, but their git state is vestigial**: they're still checked out on the old `deploy-new` branch, permanently "behind" it and dirty (rsync doesn't commit). **Don't trust `git log`/`git status` inside those two directories to tell you what's live** — grep the source for a known change instead, or check `git log -1` in `/var/www/pcuorg/monorepo`, which *is* a real, clean, up-to-date checkout of `feature/arab-contractors-union`.

### VPS layout (`srv1962001`, `187.77.172.48`) — both environments, one box

| | Staging backend | Staging frontend | Production backend | Production frontend |
|---|---|---|---|---|
| Path | `/var/www/pcuorg/api` | `/var/www/pcuorg/front` | `/var/www/pcuorg/production/api` | `/var/www/pcuorg/production/front` |
| Serves | `api.pcuorg.cloud` | `staging.pcuorg.cloud` | `api-production.pcuorg.cloud` | `pcuorg.cloud`, `www`, `production.pcuorg.cloud` |
| DB | `pcuorg` | — | `pcuorg_production` | — |

Data isolation is complete (separate DB + DB user that is *denied* on the other database, separate Redis db indexes and key prefixes, separate `APP_KEY` so tokens don't cross, separate storage dirs). What they share is CPU/RAM and a single PHP-FPM pool — heavy load on staging can *slow* production, though it cannot corrupt it.

All four `deploy-vps.sh` scripts back up before touching anything and roll back on failure. The API ones abort if the DB dump is incomplete; the frontend ones restore the previous `dist/` if the build fails. Each also **refuses to run against the wrong environment** by checking `DB_DATABASE` / `VITE_API_BASE_URL` first — don't disable that guard.

**Do not use [deploy.sh](arab-contractors-union-api/deploy.sh)** — it targets the decommissioned InMotion cPanel host (`~/acu-api`, branch `development`, `ea-php82`), unrelated to this pipeline.

`GET /` returns 404 by design; smoke-test the API with `GET /api/v1/tenders-public` (public, no auth).

`VITE_API_BASE_URL` in `.env.production` is **baked in at build time** and cannot be changed afterwards — verify it before a push triggers a build.

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

### Contractor push notifications (Firebase Cloud Messaging)

Separate from Reverb above: Reverb pushes to **admin browsers**, FCM pushes to **contractor phones**. A notification wanting both lists both channels.

`PushSenderInterface` is bound in [AppServiceProvider.php](arab-contractors-union-api/app/Providers/AppServiceProvider.php) to `FirebasePushSender` **only if** `FIREBASE_CREDENTIALS` names an existing file; otherwise it silently falls back to `LogPushSender`, **which logs and returns `true`**. A push therefore "succeeds" in every code path even when no device was reached — never treat the return value as proof of delivery. Run `php artisan push:diagnose` ([PushDiagnose.php](arab-contractors-union-api/app/Console/Commands/PushDiagnose.php)) to see which sender is actually bound, and `push:diagnose --to={membership_number}` to send a real test push.

The service-account JSON lives at `storage/app/private/firebase/` (gitignored, project `pcu-gaza`) and so is **absent from the monorepo clone the deploy rsyncs from** — `deploy-vps.sh` excludes `storage/app/private` for exactly this reason. Removing that exclude makes `rsync --delete` wipe the credentials on the next deploy and silently demote every push to log mode. Note `storage/app/private/imports/*.xlsx` *is* tracked, so that exclude also freezes those on the server; they're consumed legacy dues uploads, which is why it's safe.

Contractor-facing notifications route through `FcmChannel` (`['database', FcmChannel::class, ...]`); `FcmChannel` reads `toFcm()` if defined, else falls back to `toArray()`'s `title`/`message` keys — a `toArray()` without a `title` silently gets the generic union name as its push heading. Admin-facing ones (`PaymentSubmittedNotification`, `ContractorActivatedNotification`, `EventJoinedNotification`, the `*SubmittedNotification` family) use `broadcast` instead and must **not** get `FcmChannel` — admin `User`s have no `fcm_token`. `tests/Feature/PushChannelWiringTest.php` guards the contractor list, since a dropped channel throws no error and just stops reaching phones.

### Uploads under `storage/app/public/`

`.gitignore` excludes `contractors/`, `certificates/`, `receipts/`, `announcements/`, `events/`, `news/`. These once held committed dev placeholders; production has real uploads at the same paths. `deploy-vps.sh`'s rsync already passes `--exclude='storage/app/public'` so a normal deploy never touches these — but if anyone ever runs `git` directly inside `/var/www/pcuorg/api` or `/var/www/pcuorg/monorepo` and a `pull`/`reset` aborts with *"local changes would be overwritten"* there, back up (`tar -czf ~/backup.tar.gz storage/app/public`) before discarding anything — a bare `git checkout -- storage/` restores placeholders over real files and a subsequent pull then deletes them.

## Architecture

### Two API surfaces under one route file

Everything lives in `arab-contractors-union-api/routes/api.php` (~380 lines), split into two unrelated auth realms — check which realm a route/controller belongs to before touching auth logic:

1. **Contractor mobile/portal app** — prefix `contractor/*`, `contractor/auth/*`. Public endpoints (`login`, `verify-identity`, `set-password`, OTP flow) are throttled (`throttle:5,1` / `throttle:10,1`). Protected endpoints require `['auth:sanctum', 'contractor.active']` — the `contractor.active` middleware ([EnsureContractorIsActive.php](arab-contractors-union-api/app/Http/Middleware/EnsureContractorIsActive.php)) blocks inactive/unverified contractors even with a valid token. Login accepts `membership_number` (not email); numbering scheme is legacy `1`–`927`, new members use `{n}_g` starting at `928_g`.
2. **Admin/staff dashboard** — plain `auth:sanctum`, plus `role:admin,accountant` ([CheckRole.php](arab-contractors-union-api/app/Http/Middleware/CheckRole.php)) around financially sensitive routes (dues, exchange rates). Admin CRUD resources mostly live under `dashboard/*` prefixes (e.g. `dashboard/dues`, `dashboard/settings`, `dashboard/legal-files`).

Public, unauthenticated routes also exist for tenders, news, dynamic pages (`pages/{slug}`), and terms — these deliberately omit union-internal fields (see the `tenders-public` vs internal tender comments in the route file).

`ContractorHomeController::buildFeed()`'s combined feed (`GET contractor/home`'s `latest_updates`, and `GET contractor/home/updates`) always uses a numeric `reference_id` (the row's `id`) for every item `type`, including `news`. `GET news/{news}` takes that id directly: `NewsController::show` resolves a numeric segment by `id` and anything else by `slug`, so pre-existing slug links (e.g. the `landing/news/[slug].vue` page, `GET news`/`news/latest` list payloads which still return `slug`) keep working. Don't narrow it back to slug-only. See [Contractor_App_API.postman_collection.json](Contractor_App_API.postman_collection.json)'s `Home`/`Home Updates`/`News Details` request descriptions.

### Contractor dues ("الذمم") domain

Financial dues/settlements for contractors are a distinct subsystem from `payments`: [ContractorDueController.php](arab-contractors-union-api/app/Http/Controllers/Api/ContractorDueController.php) handles listing, summary, import (from legacy Excel via `ImportLegacyDues` command), manual settlement (`settle`), and admin-initiated payment (`payForContractor`). This is separate from the contractor-facing `payments/transfer` flow (bank transfer notification upload) in [PaymentController.php](arab-contractors-union-api/app/Http/Controllers/Api/PaymentController.php). Renewal eligibility (`contractor/renewal-eligibility`) is gated on unpaid dues — don't conflate due-settlement with membership-payment logic when editing either controller.

### Contractor `status` vs. `is_frozen` — two independent gates

Easy to conflate, confirmed intentional as of 2026-09-19: `is_frozen` is the only thing [EnsureContractorIsActive.php](arab-contractors-union-api/app/Http/Middleware/EnsureContractorIsActive.php) enforces on every authenticated contractor-portal request — it force-revokes all tokens and returns 403 (`account_frozen`, `force_logout: true`). `Contractor.status === 'suspended'` does **not** block login or portal access; it only blocks membership renewal/payment via `ContractorRequirements::renewalBlockers()` (consumed by `PaymentController::submitTransfer`), surfaced under the shared `dues_pending` error key alongside unpaid dues/penalties. Don't add a suspended-check back into the middleware without confirming it's an intentional reversal — see `tests/Feature/ContractorHomeTest.php::test_suspended_contractor_can_still_access_home` and `ContractorPaymentTest.php::test_submit_transfer_blocked_when_contractor_suspended`.

### Penalty statuses (four states, not two)

`Penalty` (financial penalties — distinct from `ContractorDue`) has four statuses: `unpaid`/`paid`/`partially_paid`/`rejected`, updated via `PATCH penalties/{id}/status` (`PenaltyController::updateStatus`, replacing the old paid-only `markPaid`/`/pay` route). `ContractorRequirements::issues()`/`renewalBlockers()` and `ContractorFinancialService::totalObligations()` both treat `partially_paid` the same as `unpaid` (counted at the remaining balance `amount - paid_amount`, not the full original amount) and both fully exclude `rejected` — any new code that reads penalty status for "is this settled"/"is this owed" must follow the same two rules, not just check for `unpaid`.

### Contractor fields/specializations/grades are DB-backed, admin-editable

[ContractorLookups.php](arab-contractors-union-api/app/Support/ContractorLookups.php) reads from the `contractor_fields`/`contractor_specializations`/`contractor_grades` tables (admin CRUD in [ContractorLookupController.php](arab-contractors-union-api/app/Http/Controllers/Api/ContractorLookupController.php) at `dashboard/contractor-{fields,specializations,grades}`), not hardcoded PHP constants — those constants now only serve as an empty-table fallback. "Delete" from the admin UI is always a soft `is_active=false` deactivate, never a hard delete, because these `code` values are consumed directly by `MembershipFeeCalculator`, PDF/Docx certificate generation, and the denormalized `contractors.specialties` JSON on existing records. `ContractorLookups::topTierFields()` (Article 37's field restriction) specifically looks up the grade coded `اولى أ` — it does not mean "whichever grade happens to have `eligible_field_codes` set".

### Model layout quirk

Most Eloquent models sit flat in `app/Models/`, but `ContractorDone` lives one level deeper at `app/Models/Models/ContractorDone.php` — check the actual namespace/`use` statement when referencing it rather than assuming the flat path.

### Frontend routing and pages

Routes are generated from the file tree under `resources/ts/pages/` (unplugin-vue-router) — there is no manual route table to edit; adding a `.vue` file there creates the route. Contractor-portal-facing pages live under `pages/contractor/` (`dashboard.vue`, `support.vue`, etc.); everything else under `pages/` (`dues/`, `contractors/`, `tenders/`, `certificates/`, ...) is the internal admin dashboard. Route names are auto-converted from PascalCase to kebab-case in [vite.config.ts](arab-contractors-union-front/vite.config.ts).

The `landing/` page directory is React (`.tsx`), not Vue — [vite.config.ts](arab-contractors-union-front/vite.config.ts) has a dedicated `force-react-compiler` transform that special-cases any file under `/landing/` ending in `.tsx`/`.jsx`. Don't assume the whole frontend is Vue-only when touching landing-page code.

### Legacy artifacts to be aware of

`CONTRACTOR_DASHBOARD_API.postman_collection.json` is tracked as deleted in the working tree and `debug.php` / a new migration are untracked — check `git status` before assuming the repo is in a clean state relative to `main`/previous commits.
