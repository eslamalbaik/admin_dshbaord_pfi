# Phase 1 Data Model: Contractor Authentication Error-Message Correctness

**Feature**: `002-contractor-auth-messaging` | **Date**: 2026-09-22

This feature introduces **no new tables, columns, or migrations**. It only reads existing contractor fields and applies guard conditions/messages. This document captures the fields that drive auth decisions and their meaning, so the logic and tests reference an agreed model.

## Entity: Contractor (existing `contractors` table)

Only the attributes relevant to authentication are listed.

| Attribute | Type | Role in this feature |
|-----------|------|----------------------|
| `id` | int (PK) | Identity; owner of Sanctum tokens. |
| `membership_number` | string | Login identifier. Canonical forms: legacy `1`–`927` (digits only) and new `{n}_g` for `n ≥ 928` (see `App\Rules\MembershipNumber`). Lookup MUST tolerate whitespace and the legacy/new forms (FR-005). |
| `phone` | string | Recovery identifier; target of the one-time recovery code. Unique per active account. |
| `password` | string (hash) \| null | Null before activation. Wrong-password check runs only after a contractor is matched (US1). |
| `phone_verified_at` | timestamp \| null | Access gate: must be non-null to log in (US2). Set on OTP verify, set-password, and successful recovery. |
| `is_frozen` | bool | The **sole access gate**. `true` → login and recovery denied with the account-frozen message (403), all tokens revoked by `EnsureContractorIsActive` on authenticated requests. |
| `status` | enum (`pending`/`active`/`suspended`/`expired`) | Business status. MUST NOT gate access. `pending` → `active` on set-password. `suspended`/`expired`/membership state gate renewal & certificates elsewhere, never login/recovery. |
| `last_login_at` | timestamp | Updated on successful login/recovery; not a gate. |
| `fcm_token` | string \| null | Updated opportunistically on login/set-password; not a gate. |

### Related (read-only, MUST NOT gate access)

| Related data | Relationship | Note |
|--------------|-------------|------|
| `activeMembership` (memberships table) | Contractor has-one active membership | Effectively empty for the current population. Gates renewal/certificates only — **never** login or password recovery. Removing it as a recovery gate is exactly commit `86329a0`. |
| Payment / dues records | Contractor has-many | Gate renewal via `ContractorRequirements`, never access. |
| Sanctum tokens | Contractor has-many | Login/recovery/set-password delete existing tokens (single active session) and issue a fresh 60-day token. |

## Derived rule: Login eligibility

`Contractor::loginEligibility()` returns an eligibility decision from **exactly two** checks, evaluated in order:

1. `is_frozen === true` → not eligible, `account_frozen`, 403.
2. `phone_verified_at === null` → not eligible, `phone_not_verified`, 403.
3. otherwise → eligible.

No membership, `status`, or payment condition may appear here. This rule is invoked by `login()` **after** the contractor is matched and the password is verified.

## State transitions (unchanged, shown for context)

```text
pending ──set-password──▶ active
  │                         ▲
  │                         │ (successful login / recovery does NOT change status)
  └── phone_verified_at set on OTP verify / set-password / successful recovery

is_frozen: toggled only by admin action (out of scope here); true ⇒ access denied everywhere.
```

## Message catalogue (source of truth: `App\Support\ApiMessages` + recovery inline strings)

| Situation | error_key | HTTP | Arabic message |
|-----------|-----------|------|----------------|
| Membership number matches no contractor (login) | `no_membership` | 404 | لا توجد لديك عضوية في الاتحاد |
| Matched contractor, wrong password (login) | `invalid_credentials` | 401 | كلمة المرور خاطئة يرجى التحقق منها |
| Account frozen (login / recovery) | `account_frozen` / `account_inactive` | 403 | حسابك مجمّد. تواصل مع الاتحاد لمزيد من المعلومات |
| Phone not verified (login eligibility) | `phone_not_verified` | 403 | لم يتم تفعيل رقم جوالك بعد… |
| Phone not registered (recovery) | `contractor_not_found` | 404 | لا يوجد حساب مرتبط برقم الجوال المُدخل |
| Invalid/expired recovery code | `invalid_otp` | 422 | رمز التحقق غير صحيح أو انتهت صلاحيته |

> The `no_membership` message is **reserved for the first row only** (unknown membership number). The wrong-password row changes wording from the current generic "بيانات الاعتماد غير صحيحة" to the password-specific string above (US1 / FR-002), while keeping the `invalid_credentials` key for client compatibility.
