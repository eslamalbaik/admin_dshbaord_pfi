# Phase 0 Research: Contractor Authentication Error-Message Correctness

**Feature**: `002-contractor-auth-messaging` | **Date**: 2026-09-22

No `NEEDS CLARIFICATION` markers remained after the spec. Research here consolidates the current-code investigation that grounds the plan, so implementation starts from verified facts rather than the (stale) field screenshots.

## Decision 1 — Login must check membership existence, then password, and use a password-specific message (US1)

- **Decision**: Keep the two-step order in `ContractorAuthController::login` — (a) look up contractor by membership number → if none, `NO_MEMBERSHIP`; (b) verify password → if wrong, a **password-specific** message. Change the wrong-password string to the agreed wording "كلمة المرور خاطئة يرجى التحقق منها".
- **Rationale**: The reported bug ("wrong password shows no-membership") cannot occur with the current ordering — the current code already returns `invalid_credentials` for a matched contractor with a bad password. The only remaining gap is *wording*: today it returns `ApiMessages::INVALID_CREDENTIALS` = "بيانات الاعتماد غير صحيحة", which is generic, not the requested password-specific text. So this is a one-line message change plus a regression test, not a logic rewrite.
- **Alternatives considered**:
  - *Return the same message for both no-membership and wrong-password* (anti-enumeration): rejected — the union explicitly wants the two distinguished for member-friendliness; rate limiting (`throttle:auth`) is the accepted mitigation for enumeration here, consistent with the rest of the auth realm.
  - *Add a distinct `error_key`*: keep the existing `invalid_credentials` key; only the human string changes, so mobile clients keying on `error_key` are unaffected.

## Decision 2 — Login eligibility stays limited to `is_frozen` + `phone_verified_at` (US2)

- **Decision**: Leave `Contractor::loginEligibility()` gating on exactly `is_frozen` (→ `account_frozen`, 403) and `phone_verified_at` (→ `phone_not_verified`, 403). Do **not** add any membership/`status`/payment check.
- **Rationale**: This matches the documented invariant (CLAUDE.md: `is_frozen` is the only access gate; `status === 'suspended'` does not block access). A member who just activated (phone verified + password set) therefore passes eligibility. The "no membership after activation" report maps to an older gated flow that no longer exists in the working tree; the fix is to guarantee — via tests — that the gate never returns.
- **Alternatives considered**: *Re-add a "must have active membership" check*: rejected — it is the exact class of bug this feature removes and would re-break US3 as well.

## Decision 3 — Membership-number lookup robustness (US2, FR-005)

- **Decision**: Normalize the submitted membership number before lookup — trim whitespace (already done) and match the legacy/​new numbering scheme defined by `App\Rules\MembershipNumber` (`1`–`927` plain, `{n}_g` for ≥ `928`). At minimum trim; additionally guard against a member typing the numeric part of a `{n}_g` number without the suffix (and, defensively, non-ASCII/Arabic-Indic digits) so a "correct" number reliably matches.
- **Rationale**: `LoginRequest` only asserts `membership_number` is a present string; the controller does `where('membership_number', trim(...))` — an exact match. Any stored value that differs by suffix or digit form from what the member types yields *no contractor* → the no-membership message, which is a second, subtler way to reproduce the US2 report. Normalizing the lookup closes that door. Exact normalization rules (whether to auto-append `_g`, whether to fold Arabic-Indic digits) are an implementation detail for the tasks phase; the contract only requires that a correct number in any accepted form matches.
- **Alternatives considered**: *Enforce `MembershipNumber` validation on `LoginRequest`*: rejected as the primary fix — validation would reject a mistyped number with a format error rather than matching a valid one, and could lock out edge-case legacy values; normalization at lookup is more forgiving and lower-risk. (A format rule could still be added later but is out of scope here.)

## Decision 4 — Password recovery is not gated on membership (US3)

- **Decision**: Keep the working-tree state from commit `86329a0`: `forgotPasswordSendOtp` and `forgotPasswordReset` check only `contractor_not_found` (404) and `is_frozen` (403); no `activeMembership` gate. OTP ownership of the registered phone is the access proof.
- **Rationale**: Membership records are effectively empty for the current population, so the old `activeMembership` gate locked out ~100% of contractors from recovery — the users most in need. This mirrors login (no membership gate) and the established design. Already implemented and covered by `ForgotPasswordTest`; this feature's job is to keep it and fold it into the same regression suite/spec.
- **Alternatives considered**: *Gate recovery on `status === 'active'`*: rejected for the same reason as Decision 2 — status/membership never gates access.

## Decision 5 — Single source of truth for auth strings (FR-008)

- **Decision**: All login/eligibility messages resolve from `App\Support\ApiMessages` constants. Add/adjust the constant for the password-specific wrong-password message and use it from `login()`. Recovery messages currently live as inline strings in `ContractorRegisterController`; note them but leave inline unless the tasks phase decides to consolidate (low priority, no behavior impact).
- **Rationale**: `ApiMessages` is already the documented "single source for all interfaces". Centralizing the wrong-password string keeps wording consistent and testable, and makes the agreed copy reviewable in one place.
- **Alternatives considered**: *Hard-code the Arabic string in the controller*: rejected — violates the existing single-source convention and makes the wording drift-prone across endpoints.

## Decision 6 — Regression coverage strategy (FR-010, SC-006)

- **Decision**: Add `tests/Feature/ContractorLoginTest.php` covering US1 (wrong password → password message, unknown number → no-membership message, correct → success) and US2 (re-login after activation succeeds; frozen blocked; membership/`status` non-active still logs in; whitespace/legacy-number lookup matches). US3 stays covered by the existing `ForgotPasswordTest.php`.
- **Rationale**: The spec's headline risk is *reintroduction* of a membership access gate or the wrong message. Tests asserting the exact outcome per scenario make such a regression fail the build (SC-006). Reuse the established feature-test style (`RefreshDatabase`, `Tests\TestCase`, `Contractor::create(...)`) seen in `ForgotPasswordTest`.
- **Alternatives considered**: *Manual QA only*: rejected — these bugs already shipped once; only automated assertions prevent recurrence.

## Cross-cutting notes

- **Deployment reality**: `HEAD` (incl. `86329a0`) is **not yet pushed** to `origin/feature/arab-contractors-union`; push = deploy. Production still runs pre-fix code, which is why the tester's screenshots show `membership_inactive`. Merging/pushing this feature's branch is what actually ships the corrections.
- **Rate limiting**: the `auth` limiter is defined in `AppServiceProvider` and applied to the login route (`throttle:auth`); recovery endpoints use `throttle:5,1`/`throttle:10,1` plus `OtpCooldownException`. None of these change (FR-009).
- **No schema / no endpoint changes**: purely message + guard-condition + test work within existing routes.
