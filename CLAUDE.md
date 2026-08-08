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
