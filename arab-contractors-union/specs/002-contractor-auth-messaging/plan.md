# Implementation Plan: Contractor Authentication Error-Message Correctness

**Branch**: `002-contractor-auth-messaging` | **Date**: 2026-09-22 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-contractor-auth-messaging/spec.md`

## Summary

Three field-reported contractor-auth defects: (1) a wrong password on a valid membership number shows the misleading "no membership" message instead of a password-specific one; (2) some contractors are blocked with a "no membership" error when logging back in after activation; (3) phone-based password recovery is blocked with a "no active membership" error. The common root cause is **conflating access with membership status**: the established design gates *renewal and certificates* on membership/payment, never *access*. The technical approach is small, surgical, and message-focused — correct the login error ordering/wording, keep login eligibility limited to `is_frozen` + `phone_verified_at`, remove any membership gate from password recovery (already done for recovery in the working tree by commit `86329a0`), harden membership-number lookup, centralize the exact strings in `ApiMessages`, and lock all of it down with feature tests so a regression fails the build. No new endpoints, no schema changes.

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12)

**Primary Dependencies**: Laravel Sanctum (token auth), existing `OtpService` (recovery codes via HotSMS), `ApiResponseTrait`, `RateLimiter` (`auth` limiter in `AppServiceProvider`)

**Storage**: MariaDB — `contractors` table (`membership_number`, `phone`, `password`, `phone_verified_at`, `is_frozen`, `status`). No migration required. The `memberships` table exists but is effectively empty for the current population and must not gate access.

**Testing**: PHPUnit feature tests under `arab-contractors-union-api/tests/Feature/` (`RefreshDatabase`, `Tests\TestCase`). `ForgotPasswordTest.php` already exists and covers User Story 3.

**Target Platform**: Linux VPS (`api.pcuorg.cloud`, base `/api/v1`); consumed by the contractor mobile app + portal.

**Project Type**: Web service (REST API) — the contractor auth realm only. No frontend change is required for this feature (the app shows whatever message the API returns).

**Performance Goals**: N/A — behavior/wording correctness, not throughput. No added latency (checks are equal or fewer than today).

**Constraints**: Must preserve existing `throttle:auth` rate limiting (FR-009); must not leak account-state beyond what activation already exposes; Arabic strings must exactly match the agreed wording; must not reintroduce any membership/payment gate on access.

**Scale/Scope**: ~2 controllers (`ContractorAuthController`, `ContractorRegisterController`), 1 model method (`Contractor::loginEligibility`), 1 support class (`ApiMessages`), plus feature tests. Contractor population is the legacy `1`–`927` + new `{n}_g` numbering.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

The project constitution at `.specify/memory/constitution.md` is the **uninstantiated template** — it contains only placeholders and defines no ratified principles, so there are no formal gates to evaluate. No violations are possible against an empty constitution.

Applied instead as de-facto gates, the project's own documented invariants from `CLAUDE.md`:

- **Access is never gated on membership/payment** (only renewal/certificates are). This feature *enforces* that invariant rather than violating it. ✅ PASS
- **`is_frozen` is the sole access gate** enforced by `EnsureContractorIsActive`, and `status === 'suspended'` must not block access. This feature keeps `is_frozen` as the only access block. ✅ PASS
- **No new suspended/membership access-check may be added without confirming intentional reversal** — this feature adds none. ✅ PASS

Initial Constitution Check: **PASS** (no gates defined; project invariants upheld).

Post-Design Constitution Check: **PASS** (design in Phase 1 introduces no membership access gate and no schema/endpoint change).

## Project Structure

### Documentation (this feature)

```text
specs/002-contractor-auth-messaging/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
│   └── contractor-auth.md
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit-specify output)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

The change is confined to the existing Laravel API app; no new modules or directories are introduced.

```text
arab-contractors-union-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── ContractorAuthController.php        # login(): message ordering + wrong-password wording (US1)
│   │   │   └── ContractorRegisterController.php    # forgot-password/*: no membership gate (US3, already fixed by 86329a0)
│   │   └── Requests/Contractor/
│   │       └── LoginRequest.php                    # (unchanged) validates membership_number/password presence
│   ├── Models/
│   │   └── Contractor.php                          # loginEligibility(): is_frozen + phone_verified_at only (US2)
│   ├── Rules/
│   │   └── MembershipNumber.php                    # reference for canonical vs. legacy {n}_g forms (US2 lookup)
│   └── Support/
│       └── ApiMessages.php                         # single source of truth for auth strings (FR-008)
└── tests/
    └── Feature/
        ├── ContractorLoginTest.php                 # NEW — US1 + US2 regression coverage
        └── ForgotPasswordTest.php                  # EXISTING — US3 coverage (added by 86329a0)
```

**Structure Decision**: Single existing web-service app (`arab-contractors-union-api/`). This is a targeted correctness fix inside the contractor auth realm, so it reuses the current controller/model/support layout and adds one feature test file. The frontend (`arab-contractors-union-front/`) needs no change because it renders whatever message string the API returns.

## Complexity Tracking

> No constitution violations. No complexity deviations to justify. Section intentionally left empty.
