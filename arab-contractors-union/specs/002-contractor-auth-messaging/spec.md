# Feature Specification: Contractor Authentication Error-Message Correctness

**Feature Branch**: `002-contractor-auth-messaging`

**Created**: 2026-09-22

**Status**: Draft

**Input**: User description: Three field-reported defects in the contractor mobile/portal authentication flow where the wrong error message is shown, and where password recovery / re-login is wrongly blocked. The union's members must (a) see a password-specific message when their password is wrong rather than a misleading "you have no membership" message, (b) be able to log back in after activating their account, and (c) be able to recover their password by phone — none of which may be gated on membership or payment status.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Wrong password shows a password error, not a membership error (Priority: P1)

A registered contractor opens the app and signs in with their correct membership number but mistypes their password. Today they are told "لا توجد لديك عضوية في الاتحاد" ("you have no membership in the union"), which is alarming and wrong — it makes them believe their membership was revoked. They should instead be told plainly that the password is incorrect so they can simply retry.

**Why this priority**: This is the single most common daily authentication event (a typo), it directly frightens legitimate paying members into thinking they lost their membership, and it generates avoidable support calls. It is the highest-impact, most-frequent defect.

**Independent Test**: Attempt login with a known-good membership number and a deliberately wrong password; confirm the response is the password-incorrect message ("كلمة المرور خاطئة يرجى التحقق منها") and never the no-membership message.

**Acceptance Scenarios**:

1. **Given** a contractor exists with a set password, **When** they submit the correct membership number and an incorrect password, **Then** they see a password-incorrect message ("كلمة المرور خاطئة يرجى التحقق منها") and are not signed in.
2. **Given** no contractor exists for the submitted membership number, **When** they attempt to log in, **Then** they see the no-membership message ("لا توجد لديك عضوية في الاتحاد") — this message is reserved for an unknown/unmatched membership number only.
3. **Given** a contractor exists with a set password, **When** they submit the correct membership number and the correct password, **Then** they are signed in successfully.

---

### User Story 2 - A contractor can log back in after activating their account (Priority: P1)

A contractor completes account activation (verifies their phone and sets a password), then later logs out and returns to sign in again with the same correct membership number and password. Today some of these members are blocked with a "no membership" message even though their credentials are correct and their account is fully active. They must be able to sign back in.

**Why this priority**: Being locked out of an account you just successfully activated destroys trust in the app and blocks a member from every portal feature. It affects newly onboarded members at the worst possible moment.

**Independent Test**: Activate a fresh account, log out, then log in again with the same membership number and password; confirm sign-in succeeds without any membership-related error.

**Acceptance Scenarios**:

1. **Given** a contractor who has activated their account (phone verified, password set) and then logged out, **When** they log in again with the correct membership number and password, **Then** they are signed in successfully.
2. **Given** an active, credential-correct contractor whose union membership record or payment status is not "active", **When** they log in, **Then** access is still granted — membership/payment state does not block sign-in.
3. **Given** a contractor whose account is frozen by an administrator, **When** they log in with correct credentials, **Then** access is denied with the account-frozen message (frozen is the only state that blocks access).
4. **Given** a membership number entered with surrounding whitespace or using the legacy suffixed numbering scheme (e.g. a "_g" suffixed number), **When** it matches a real contractor after normalization, **Then** the contractor is found and sign-in proceeds to the password check.

---

### User Story 3 - A contractor can recover their password by phone (Priority: P1)

A contractor who forgot their password enters the phone number registered on their account to receive a one-time recovery code. Today they are blocked with "لا يمكن استعادة كلمة المرور — لا توجد لديك عضوية فعّالة في الاتحاد" ("cannot recover password — you have no active membership"). Proving ownership of the registered phone via the one-time code must be sufficient to reset the password.

**Why this priority**: The members who need password recovery are, by definition, already locked out; blocking recovery on membership status locks out essentially all of them (membership records are effectively empty for the current population), leaving no self-service way back into the account.

**Independent Test**: Request a recovery code for a registered phone number belonging to a contractor with no active membership record; confirm the code is sent and the subsequent reset succeeds and signs the contractor in.

**Acceptance Scenarios**:

1. **Given** a contractor whose phone number is registered and whose account is not frozen, **When** they request a password-recovery code for that phone, **Then** the code is sent — regardless of membership or payment status.
2. **Given** a valid recovery code sent to the registered phone, **When** the contractor submits the code with a new password, **Then** the password is reset and they are signed in.
3. **Given** a phone number not registered to any contractor, **When** a recovery code is requested, **Then** a "no account for this phone" message is shown.
4. **Given** a frozen contractor, **When** they request a recovery code, **Then** the request is denied with the account-frozen message.
5. **Given** an incorrect or expired recovery code, **When** it is submitted, **Then** an invalid-code message is shown and the password is unchanged.

---

### Edge Cases

- A contractor exists but has never set a password (mid-activation): login with any password must not reveal a password vs. membership distinction that leaks account state beyond what activation already exposes; the account-not-yet-registered path is handled by the activation flow, not login.
- Membership number submitted with leading/trailing whitespace, or in the legacy suffixed form, must resolve to the same contractor as the canonical value.
- Repeated wrong-password or repeated recovery-code requests must remain subject to existing rate limiting so the corrected messages cannot be abused for enumeration or brute force.
- A recovery code proves phone ownership; a contractor who had never verified their phone but successfully completes recovery is thereby considered phone-verified.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: On login, the "no membership" message MUST be shown only when the submitted membership number matches no contractor. It MUST NOT be shown for a matched contractor whose password is wrong.
- **FR-002**: On login with a matched contractor and an incorrect password, the system MUST return a password-specific message stating the password is incorrect ("كلمة المرور خاطئة يرجى التحقق منها") and MUST NOT sign the user in.
- **FR-003**: Login eligibility MUST be gated only on the account being unfrozen and the phone being verified. It MUST NOT be gated on the presence or status of a union membership record or on payment/dues status.
- **FR-004**: A contractor who has activated their account (phone verified, password set) MUST be able to log in with correct credentials after logging out, with no membership-related error.
- **FR-005**: Membership-number lookup on login MUST tolerate surrounding whitespace and the legacy suffixed numbering scheme so that a correct number is reliably matched.
- **FR-006**: Password recovery (both requesting a code and resetting with it) MUST NOT be blocked by membership or payment status. Proof of ownership of the registered phone via the one-time code is the sole access requirement, aside from the frozen-account block.
- **FR-007**: A frozen account MUST be blocked from login and from password recovery, with the account-frozen message; frozen is the only account state that denies access.
- **FR-008**: Error messages for the auth realm MUST come from a single agreed source of truth so that the "no membership" and "wrong password" wordings are consistent across every endpoint that can emit them.
- **FR-009**: Existing rate limiting on login, recovery-code requests, and code verification MUST remain in force after the message corrections.
- **FR-010**: The corrected behavior MUST be covered by automated tests that assert the exact message/outcome for each scenario in User Stories 1–3, so a future change that reintroduces a membership gate or the wrong message fails the build.

### Key Entities

- **Contractor account**: The member's login identity. Relevant attributes: membership number (canonical + legacy suffixed forms), phone number, whether a password is set, whether the phone is verified, whether the account is frozen, and its status. Membership/payment records are related but MUST NOT influence access.
- **Recovery code**: A short-lived one-time code sent to the registered phone that proves phone ownership for password reset.
- **Auth message set**: The agreed catalogue of member-facing authentication messages (no-membership, wrong-password, account-frozen, phone-not-verified, invalid-code, etc.).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of login attempts with a correct membership number and a wrong password return the password-incorrect message and 0% return the no-membership message.
- **SC-002**: 100% of login attempts with an unknown membership number return the no-membership message (the message remains meaningful, just correctly scoped).
- **SC-003**: A contractor who just activated their account can log back in successfully in 100% of attempts with correct credentials, with no membership-related error.
- **SC-004**: 100% of password-recovery requests for a registered, unfrozen phone succeed in sending a code and completing a reset, independent of membership or payment status.
- **SC-005**: Support contacts about "the app says I have no membership" after a wrong password or during password recovery drop to effectively zero once deployed.
- **SC-006**: Automated tests exist and pass for every acceptance scenario in User Stories 1–3, and would fail if a membership gate or the incorrect message were reintroduced.

## Assumptions

- The corrected Arabic wordings are the ones agreed with the union team: no-membership = "لا توجد لديك عضوية في الاتحاد", wrong-password = "كلمة المرور خاطئة يرجى التحقق منها"; the plan phase confirms exact final strings.
- This feature is scoped to the contractor authentication realm only (login, phone-based password recovery, and the login-eligibility rule). The separate admin/staff login realm is out of scope.
- Membership and payment status legitimately continue to gate membership renewal and certificate issuance elsewhere; this feature only removes them as gates on **access** (login and recovery), consistent with the established system design.
- The account-frozen block is the intended and sole access gate and is retained unchanged.
- Two of the three defects have already been partly or fully addressed in the current codebase but are not yet deployed; this spec is the source of truth for the intended behavior and its regression coverage regardless of current implementation state.
- Existing one-time-code delivery, session/token issuance, and rate-limiting mechanisms are reused as-is.
