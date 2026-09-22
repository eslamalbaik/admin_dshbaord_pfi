# Quickstart & Validation: Contractor Authentication Error-Message Correctness

**Feature**: `002-contractor-auth-messaging` | **Date**: 2026-09-22

How to validate the three defects are fixed. References: [spec.md](./spec.md), [contracts/contractor-auth.md](./contracts/contractor-auth.md), [data-model.md](./data-model.md).

## Prerequisites

```bash
cd arab-contractors-union-api
composer install
cp .env.example .env && php artisan key:generate      # first time only
php artisan migrate                                    # test DB uses RefreshDatabase
```

- API base for manual checks: `http://localhost:8000/api/v1` (`php artisan serve`).
- In non-production, recovery/registration responses include `otp_preview`, so the phone flow is testable without live SMS.

## Automated validation (primary — SC-006)

```bash
cd arab-contractors-union-api
php artisan test --filter=ContractorLoginTest      # NEW — User Stories 1 & 2
php artisan test --filter=ForgotPasswordTest       # EXISTING — User Story 3
```

Expected: all pass. These assert the exact `error` key / HTTP status / message per the contract's Guarantees (G-L*, G-S*, G-R*). A regression that reintroduces a membership access gate or the generic/no-membership message must make these fail.

Coverage the suite must include:

- **US1**: correct number + wrong password → 401 `invalid_credentials`, message "كلمة المرور خاطئة يرجى التحقق منها" (G-L1); unknown number → 404 `no_membership` (G-L2); correct → 200 (with token).
- **US2**: activate → logout → login again → 200 (G-L3); frozen → 403 `account_frozen`; non-active `status` / no membership row but correct creds + verified phone → 200 (G-L3); whitespace / legacy `{n}_g` number resolves (G-L4).
- **US3** (existing): send-otp for registered non-frozen phone with zero membership rows → 200 (G-S1); full reset → 200 + token, password usable (G-R1); unknown phone → 404; frozen → 403; bad code → 422.

## Manual smoke (optional)

Assumes a seeded contractor with `membership_number` (e.g. `9551_g`), `phone` (e.g. `592373805`), a set password, `phone_verified_at` set, `is_frozen=false`, and **no** membership row.

1. **US1 — wrong password**
   ```bash
   curl -s -X POST http://localhost:8000/api/v1/contractor/auth/login \
     -H 'Accept: application/json' \
     -d 'membership_number=9551_g' -d 'password=WRONG'
   ```
   Expect `status_code: 401`, `error: invalid_credentials`, message = "كلمة المرور خاطئة يرجى التحقق منها" — **not** the no-membership text.

2. **US1 — unknown number**: repeat with `membership_number=000000` → `404 / no_membership / لا توجد لديك عضوية في الاتحاد`.

3. **US2 — re-login after activation**: with the correct password → `200`, `token` present, no membership error.

4. **US3 — recovery, no membership**
   ```bash
   curl -s -X POST http://localhost:8000/api/v1/contractor/auth/forgot-password/send-otp \
     -H 'Accept: application/json' -d 'phone=592373805'
   ```
   Expect `200` and (non-prod) `otp_preview`; then POST `/forgot-password/reset` with that code + a new password → `200` + token. Never `membership_inactive`.

## Deployment note (why the field reports persist)

`HEAD` and the recovery fix `86329a0` are **not yet pushed** to `origin/feature/arab-contractors-union`; on this repo, push = production deploy. Until this work is merged and pushed, production keeps returning the old messages regardless of local correctness. After merge, re-run steps 1–4 against `https://api.pcuorg.cloud/api/v1` to confirm the live fix. Keep [Contractor_App_API.postman_collection.json](../../../Contractor_App_API.postman_collection.json) in sync (the `membership_inactive` row was already removed).
