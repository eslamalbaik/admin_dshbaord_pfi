# Feature Specification: App Notifications — Announcements & Subscription Reminders

**Feature Branch**: `001-app-notifications`

**Created**: 2026-09-22

**Status**: Draft

**Input**: User description: "خاصية الاشعارات إلى التطبيق: تعميم جديد، تذكير بدفع الاشتراك، تذكير بانتهاء الاشتراك السنوي"

## Overview

Contractors using the mobile/portal app should receive timely notifications so they stay informed and act on time. This feature covers three notification types the union wants to deliver to contractors:

1. **New announcement/circular ("تعميم جديد")** — notify contractors when the union publishes a new announcement.
2. **Subscription payment reminder ("تذكير بدفع الاشتراك")** — remind contractors who owe an outstanding membership payment to pay it.
3. **Annual subscription expiry reminder ("تذكير بانتهاء الاشتراك السنوي")** — warn contractors that their annual membership is about to expire (or has expired) so they renew.

Each notification is delivered to the contractor's phone as a push notification **and** appears in the contractor's in-app notification list so it can be reviewed later even if the push is missed.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Contractor is notified of a new announcement (Priority: P1)

When the union publishes a new announcement/circular, every contractor who should see it receives a notification. Tapping it opens the announcement so the contractor can read it in full.

**Why this priority**: The union's primary reason for "notifications in the app" is broadcasting official communications. Announcements already exist in the system but reach nobody proactively today — this closes the biggest gap and delivers immediate, repeatable value every time the union posts.

**Independent Test**: Publish a new announcement as an admin, then confirm a targeted active contractor receives a push notification titled with the announcement subject and finds a matching entry in their in-app notification list that opens the announcement.

**Acceptance Scenarios**:

1. **Given** an active contractor with the app installed, **When** an admin publishes a new announcement targeted at that contractor's audience, **Then** the contractor receives a push notification and a matching in-app notification referencing that announcement.
2. **Given** a contractor taps the announcement notification, **When** it opens, **Then** the full announcement content is displayed.
3. **Given** an announcement is saved as a draft (not published), **When** it is created, **Then** no contractor is notified.
4. **Given** an announcement is later edited (not newly published), **When** the edit is saved, **Then** contractors are not re-notified for the same announcement.

---

### User Story 2 - Contractor is reminded to pay an outstanding subscription (Priority: P2)

A contractor who has an unpaid/outstanding membership subscription receives a reminder to pay, on a defined schedule, until the balance is settled.

**Why this priority**: Directly supports revenue collection and reduces manual follow-up, but depends on notification delivery (US1's channels) being in place and is lower urgency than establishing the core announcement channel.

**Independent Test**: Mark a test contractor as having an outstanding subscription payment, trigger the reminder run, and confirm the contractor receives a push + in-app reminder; then settle the payment and confirm no further reminder is sent on the next run.

**Acceptance Scenarios**:

1. **Given** a contractor with an outstanding subscription payment, **When** the reminder run executes, **Then** the contractor receives a payment reminder (push + in-app).
2. **Given** a contractor whose subscription is fully paid, **When** the reminder run executes, **Then** the contractor receives no payment reminder.
3. **Given** a contractor already reminded today, **When** the reminder run executes again the same day, **Then** the contractor is not reminded twice for the same obligation.
4. **Given** a frozen contractor account, **When** the reminder run executes, **Then** the reminder behavior follows the same eligibility rules the union applies to other contractor notifications.

---

### User Story 3 - Contractor is warned before annual subscription expiry (Priority: P3)

A contractor whose annual membership is approaching its expiry date receives advance warning so they can renew before losing active status.

**Why this priority**: High business value for retention, but reminder infrastructure for membership expiry already partially exists, so this story is largely a confirmation/extension rather than net-new capability — lower delivery risk, sequenced last.

**Independent Test**: Set a test contractor's membership to expire within the reminder window, run the reminder job, and confirm the contractor receives an expiry warning (push + in-app) that references the expiry date; contractors well outside the window receive nothing.

**Acceptance Scenarios**:

1. **Given** a contractor whose membership expires within the configured warning window, **When** the reminder run executes, **Then** the contractor receives an expiry-warning notification stating when membership expires.
2. **Given** a contractor whose membership expires far in the future (outside the window), **When** the reminder run executes, **Then** no expiry warning is sent.
3. **Given** a contractor who renews after being warned, **When** the next reminder run executes, **Then** no further expiry warning is sent for that cycle.

---

### Edge Cases

- **No registered device**: A contractor with no active push device still gets the in-app notification; the missing push is not treated as a hard failure.
- **Delivery failure**: If push delivery fails for a device, it does not block the in-app notification or reminders to other contractors.
- **Duplicate runs**: Re-running a reminder job (e.g., after a crash) does not produce duplicate reminders for the same obligation/cycle on the same day.
- **Inactive / unverified / frozen contractors**: Notification eligibility respects the union's existing active-account rules; ineligible contractors are not spammed.
- **Announcement audience with no members**: Publishing an announcement whose audience currently has zero eligible contractors completes without error and notifies nobody.
- **Localization**: Notification titles and bodies are presented in Arabic (the contractor-facing language).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST notify eligible contractors when a new announcement is published, delivering both a push notification and a persisted in-app notification.
- **FR-002**: The announcement notification MUST carry the announcement's title/subject as its heading and reference the specific announcement so tapping it opens that announcement.
- **FR-003**: System MUST NOT notify contractors for announcements that are drafts/unpublished, and MUST NOT re-notify for an already-published announcement that is merely edited.
- **FR-004**: System MUST determine announcement recipients based on the announcement's intended audience (e.g., all active contractors, or a category/audience segment when specified).
- **FR-005**: System MUST send a subscription payment reminder to each contractor who has an outstanding membership subscription payment, delivering push + in-app notification.
- **FR-006**: System MUST NOT send a payment reminder to a contractor whose subscription is fully paid / has no outstanding obligation.
- **FR-007**: System MUST send an annual subscription expiry reminder to contractors whose membership expires within a defined warning window, stating the expiry date, delivering push + in-app notification.
- **FR-008**: System MUST NOT send an expiry reminder to contractors whose membership is outside the warning window, and MUST stop reminders once the contractor renews for that cycle.
- **FR-009**: Reminder jobs (payment and expiry) MUST be idempotent within their run period — re-execution MUST NOT produce duplicate reminders for the same contractor/obligation/cycle.
- **FR-010**: All three notification types MUST persist an in-app notification record so contractors can review notifications later even if the push was missed or the device was offline.
- **FR-011**: Notification eligibility MUST respect the union's existing contractor-account rules (active/verified/frozen status) so ineligible accounts are not notified.
- **FR-012**: Contractor-facing notification titles and bodies MUST be in Arabic.
- **FR-013**: A push delivery failure for one contractor MUST NOT prevent notifications to other contractors or block the persisted in-app record.
- **FR-014**: Reminder timing/cadence (how many days before expiry, and how often payment reminders repeat) MUST be configurable by the union without a code change, or governed by a clearly documented fixed schedule if configuration is out of scope for v1.

- **FR-015**: The payment reminder (US2) targets contractors in the grace period after membership expiry, prompting them to pay and reactivate; the expiry reminder (US3) warns before expiry so they can renew proactively. This aligns with the existing grace-period and renewal-reminder infrastructure.

### Key Entities *(include if feature involves data)*

- **Announcement**: An official communication published by the union; has a title/subject, body, publish state (draft/published), and an intended audience. Already exists in the system.
- **Contractor**: The notification recipient; has an account status (active/verified/frozen), a membership subscription with an expiry date, and zero or more registered push devices.
- **Subscription / Membership Obligation**: The contractor's annual membership standing — whether it is paid, outstanding, and when it expires. Drives payment and expiry reminders.
- **Notification (in-app record)**: A persisted, per-contractor record of a delivered notification (type, title, body, reference to the source item, read/unread state) shown in the contractor's notification list.
- **Push Device Token**: A contractor's registered device target for push delivery; may be absent.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: When the union publishes an announcement, 100% of eligible active contractors with a registered device receive a push within 5 minutes, and 100% of eligible contractors have a matching in-app notification.
- **SC-002**: Tapping an announcement notification opens the correct announcement for at least 99% of taps (no broken/incorrect references).
- **SC-003**: 100% of contractors with an outstanding subscription payment receive at least one payment reminder per configured cycle; 0% of fully-paid contractors receive one.
- **SC-004**: 100% of contractors whose membership expires within the warning window receive an expiry reminder that names the correct expiry date; 0% outside the window are reminded.
- **SC-005**: Re-running any reminder job on the same day produces 0 duplicate reminders for the same contractor/obligation.
- **SC-006**: Renewal/payment completion measurably increases among reminded contractors versus the pre-feature baseline (target: reduce lapsed/unpaid memberships by a union-defined percentage within one renewal cycle).

## Assumptions

- The app already has a contractor-facing in-app notification list and a push-delivery channel; this feature adds new notification types to that existing capability rather than building notification delivery from scratch.
- Announcements are an existing, admin-managed content type; "new announcement" means the moment an announcement transitions to a published state.
- "Eligible contractor" follows the union's existing account-status rules for who may receive contractor notifications (active/verified, not frozen), consistent with how the app already gates contractor-facing behavior.
- Contractor-facing content is Arabic; no multi-language notification requirement for v1.
- Notification timing for reminders runs on a scheduled (batch) basis, not instantly per contractor, and does not require real-time precision.
- Some expiry-reminder capability already exists and may be extended/reused rather than rebuilt; the payment reminder and the announcement push are the primary net-new work (pending FR-015 clarification).
- Delivery is best-effort: a missing device token or a single failed push is logged and does not count as a feature failure, because the in-app record is the durable channel.
