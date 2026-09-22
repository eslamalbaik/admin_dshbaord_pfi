# API Contract: Contractor Auth Realm (feature 002)

**Feature**: `002-contractor-auth-messaging` | **Date**: 2026-09-22

Contracts for the three affected endpoints. All live under base `/api/v1`, contractor auth realm (public/throttled — no token required to call these). Response envelope follows `ApiResponseTrait`: success = `{ status: true, message, data?, token? }`; error = `{ status: false, message, status_code, error }`.

Only the behavior this feature guarantees is specified; unrelated fields are elided.

---

## POST `/contractor/auth/login`

Middleware: `throttle:auth` (rate limiting MUST remain — FR-009).

### Request

| Field | Rules |
|-------|-------|
| `membership_number` | required, string (canonical `1`–`927` or `{n}_g`; tolerated with surrounding whitespace) |
| `password` | required, string |
| `fcm_token` | nullable, string |
| `device_name` | nullable, string ≤ 120 |

### Responses

| Case | HTTP | `error` | `message` |
|------|------|---------|-----------|
| Membership number matches no contractor (after normalization) | 404 | `no_membership` | لا توجد لديك عضوية في الاتحاد |
| Matched contractor, **wrong password** | 401 | `invalid_credentials` | كلمة المرور خاطئة يرجى التحقق منها |
| Matched, correct password, `is_frozen = true` | 403 | `account_frozen` | حسابك مجمّد… |
| Matched, correct password, `phone_verified_at = null` | 403 | `phone_not_verified` | لم يتم تفعيل رقم جوالك بعد… |
| Matched, correct password, eligible | 200 | — | تم تسجيل الدخول بنجاح. + `token` + lite profile |

### Guarantees (testable)

- **G-L1**: A correct `membership_number` + wrong `password` NEVER returns `no_membership`; it returns `invalid_credentials` with the password-specific Arabic message.
- **G-L2**: `no_membership` is returned ONLY when no contractor matches the (normalized) number.
- **G-L3**: Eligibility gate is `is_frozen` + `phone_verified_at` only — a contractor with non-active `status` / no membership record but correct credentials and verified phone logs in (200).
- **G-L4**: A membership number with surrounding whitespace, or a valid legacy/new-form number, resolves to the same contractor.
- **G-L5**: Order of checks: match → password → eligibility. (A frozen account with a wrong password still surfaces `invalid_credentials`, not `account_frozen`, since password is checked first — matches current code.)

---

## POST `/contractor/auth/forgot-password/send-otp`

Middleware: throttled (`throttle:5,1`-class) + `OtpCooldownException`.

### Request

| Field | Rules |
|-------|-------|
| `phone` | required, string (registered phone; tolerated with surrounding whitespace) |

### Responses

| Case | HTTP | `error` | `message` |
|------|------|---------|-----------|
| No contractor for this phone | 404 | `contractor_not_found` | لا يوجد حساب مرتبط برقم الجوال المُدخل |
| Contractor `is_frozen = true` | 403 | `account_inactive` | حسابك مجمّد… |
| Cooldown active | 429 | `otp_cooldown` | (cooldown message) + `expires_in` |
| Registered, not frozen (ANY membership/status) | 200 | — | تم إرسال رمز التحقق… + `expires_in` (+ `otp_preview` in non-prod) |

### Guarantees (testable)

- **G-S1**: A registered, non-frozen phone receives a code REGARDLESS of membership or payment status. `membership_inactive` MUST NOT be returned (removed by `86329a0`).
- **G-S2**: Only `is_frozen` blocks a registered phone from recovery.

---

## POST `/contractor/auth/forgot-password/reset`

### Request

| Field | Rules |
|-------|-------|
| `phone` | required, string |
| `otp` | required, string, size 6 |
| `password` | required, string, min 8, confirmed |

### Responses

| Case | HTTP | `error` | `message` |
|------|------|---------|-----------|
| No contractor for this phone | 404 | `contractor_not_found` | لا يوجد حساب مرتبط برقم الجوال المُدخل |
| Contractor `is_frozen = true` | 403 | `account_inactive` | حسابك مجمّد… |
| Wrong/expired code | 422 | `invalid_otp` | رمز التحقق غير صحيح أو انتهت صلاحيته |
| Valid code + valid new password | 200 | — | تم استعادة وتغيير كلمة المرور… + `token` |

### Guarantees (testable)

- **G-R1**: Reset succeeds for a registered, non-frozen contractor with a valid code and NO active membership record; the new password works on subsequent login and a session token is issued.
- **G-R2**: Successful reset sets `phone_verified_at` if it was null (recovery proves phone ownership).
- **G-R3**: `membership_inactive` MUST NOT be returned by this endpoint.

---

## Non-goals / invariants preserved

- No new endpoints, request fields, or response envelope changes.
- `error_key` values are unchanged (only the `invalid_credentials` human message changes) so existing mobile clients keying on `error` continue to work.
- Existing rate limits and OTP cooldowns are unchanged.
- Admin/staff login realm (`/…/login` via `AuthController`) is out of scope.
