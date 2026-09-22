# Implementation Plan: App Notifications — Announcements & Subscription Reminders

**Branch**: `001-app-notifications` | **Date**: 2026-09-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-app-notifications/spec.md`

## Summary

Deliver three contractor-facing notification types (announcement broadcasts, subscription payment reminders, annual expiry reminders) via push notifications and persisted in-app records, leveraging existing Firebase Cloud Messaging (FCM) channel for contractors and extending the Notification entity to track all three types with idempotent delivery semantics.

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12 backend), Vue 3 + TypeScript (frontend)

**Primary Dependencies**: 
- Backend: Laravel 12, Sanctum auth, Firebase Cloud Messaging (FCM), MariaDB
- Frontend: Vue 3, Pinia, Vite, TypeScript

**Storage**: MariaDB — existing Announcement, Contractor, Notification tables (new fields TBD)

**Testing**: PHPUnit (backend), Vitest (frontend)

**Target Platform**: 
- Backend: VPS (Linux/PHP-FPM, Nginx)
- Frontend: iOS/Android mobile app + Vue 3 web portal

**Project Type**: Full-stack Laravel/Vue SPA with mobile app

**Performance Goals**: 
- Announcement broadcast: 100% of eligible contractors notified within 5 minutes of publish
- Reminder jobs: sub-second per-contractor processing (batch idempotent run)

**Constraints**: 
- No device token → push fails gracefully, in-app record persists
- Frozen/inactive contractors excluded from all notifications (respect existing account rules)
- Re-running reminder jobs same-day → 0 duplicate reminders (idempotency via run-date tracking)

**Scale/Scope**: ~1000–10000 active contractors, 3 notification types, 2 reminder jobs (payment + expiry)

## Constitution Check

*No project-wide constitution exists yet; this feature is the first.* Defer constitution creation to a separate task; this plan proceeds with reasonable defaults for Laravel/Vue projects:

- **Test-First**: Feature tests written per acceptance scenario before implementation
- **Database-Backed**: Notification records persisted to DB for durability and contractor review
- **Graceful Degradation**: Missing device token or failed push does not block in-app notification
- **Existing Infrastructure Reuse**: FCM channel already exists; announcement model exists; extend both rather than rebuild

## Project Structure

### Documentation (this feature)

```text
specs/001-app-notifications/
├── spec.md              # Feature specification ✓
├── plan.md              # This file (Phase 0 planning)
├── research.md          # Phase 0 research output
├── data-model.md        # Phase 1 data entity design
├── quickstart.md        # Phase 1 validation scenarios + manual test steps
└── contracts/
    └── notification-push.md  # Phase 1 FCM push contract schema
```

### Source Code (monorepo)

```text
arab-contractors-union-api/
├── app/
│   ├── Models/
│   │   ├── Notification.php      # Persisted in-app notification (new or extended)
│   │   └── [existing: Announcement, Contractor]
│   ├── Http/Controllers/Api/
│   │   ├── NotificationController.php  # Contractor list/read endpoint
│   │   └── [existing: AnnouncementController]
│   ├── Events/
│   │   ├── AnnouncementPublished.php    # Trigger announcement broadcast
│   │   ├── SubscriptionPaymentReminder.php  # Trigger payment reminder
│   │   └── SubscriptionExpiryReminder.php   # Trigger expiry reminder
│   ├── Listeners/
│   │   ├── SendAnnouncementNotifications.php
│   │   ├── SendPaymentReminderNotifications.php
│   │   └── SendExpiryReminderNotifications.php
│   ├── Console/Commands/
│   │   ├── SendGracePeriodReminders.php  # Existing, extend for idempotency tracking
│   │   └── SendRenewalReminders.php      # Existing, extend for idempotency tracking
│   ├── Channels/
│   │   └── FcmChannel.php  # Existing FCM channel
│   └── Notifications/
│       ├── AnnouncementNotification.php
│       ├── SubscriptionPaymentReminderNotification.php
│       └── SubscriptionExpiryReminderNotification.php
├── database/
│   └── migrations/
│       └── [new] Create/extend notifications table
└── tests/Feature/
    ├── AnnouncementNotificationTest.php
    ├── PaymentReminderTest.php
    └── ExpiryReminderTest.php

arab-contractors-union-front/
├── src/
│   ├── pages/contractor/
│   │   └── notifications.vue  # Contractor notification list (new or extended)
│   ├── services/
│   │   └── notificationService.ts  # Fetch and mark-read API calls
│   └── components/
│       ├── ContractorNotificationList.vue  # Display in-app notifications
│       └── NotificationItem.vue  # Single notification card with tap handler
└── tests/
    └── [integration tests for notification-list rendering]
```

**Structure Decision**: Full-stack feature spanning both backend API and mobile/web frontend. Backend handles notification delivery (via FCM + DB persistence); frontend fetches and displays contractor notification list. Two reminder commands are extended (not rebuilt) to track idempotency.

## Complexity Tracking

No violations identified. Feature is a straightforward extension of existing infrastructure:
- Announcement model already exists
- FCM channel already exists
- Reminder commands already exist (extend with idempotency)
- Notification table design follows existing patterns

---

## Phases

### Phase 0 - Research
**Objective**: Clarify open questions and document idempotency/timing decisions.

**Outputs**:
- `research.md` — Decisions on:
  - When to send each reminder (relative to payment due date, expiry date)
  - Idempotency approach (run-date tracking in table, or other)
  - Existing Notification table schema (inspect current fields)
  - Announcement audience interpretation (all contractors vs. category segments)
  - Frozen contractor eligibility rules (respect existing middleware or new check?)

---

### Phase 1 - Design
**Objective**: Nail down data schema and push contracts before implementation.

**Outputs**:
- `data-model.md` — Entities and fields:
  - Notification table: `id`, `contractor_id`, `type` (enum: announcement/payment/expiry), `title`, `body`, `reference_id` (points to Announcement or null), `read_at`, `created_at`
  - Notification type enum or string check
  - Migration for new/modified fields

- `contracts/notification-push.md` — FCM payload contract:
  - Title, body, click action, reference ID or deeplink
  - Android vs. iOS differences (if any)

- `quickstart.md` — Validation scenarios (can be run manually or via feature test):
  1. Publish announcement → eligible contractor receives push + in-app record
  2. Mark contractor as having outstanding payment → run payment reminder → push + in-app
  3. Set contractor expiry within window → run expiry reminder → push + in-app
  4. Re-run job same day → 0 duplicates
  5. Frozen contractor → not notified (despite outstanding payment)

---

### Phase 2 - Implementation (NOT in this plan, handled by /speckit-tasks)
**Tasks** (auto-generated by `/speckit-tasks`):
1. DB Migration: Notification table (or extend existing)
2. Announcement → AnnouncementPublished event dispatcher
3. AnnouncementPublished listener → build eligible contractor list → send broadcast (push + in-app)
4. Payment reminder command: idempotency check → contractor loop → send (push + in-app)
5. Expiry reminder command: idempotency check → contractor loop → send (push + in-app)
6. NotificationController: `GET contractor/notifications` (paginated, with read/unread status)
7. Frontend: ContractorNotificationList component + fetch service
8. Feature tests: 3 main scenarios (announcement, payment, expiry) + edge cases (frozen, no device, dups)

---

## Key Decisions

1. **Leverage Existing Infrastructure**: Reuse `FcmChannel`, existing `Announcement` model, existing reminder commands. No net-new notification channel required.

2. **Idempotency via Tracking**: Each reminder job tracks its run date per contractor (or globally, TBD in research) to prevent same-day duplicates. Schema TBD — could be a `notification_runs` lookup table or a field on `contractors` table.

3. **Graceful Push Failure**: Missing device token or FCM failure → log and continue. In-app record is always persisted, push is best-effort.

4. **Announcement Audience**: Initially assume "all active contractors" unless audience field is already present on Announcement model; if it is, query that audience before broadcasting.

5. **Account Status Gating**: Respect existing `EnsureContractorIsActive` middleware rules — frozen contractors get no notifications. Suspended contractors follow existing renewal-blocker logic (payment reminder may still reach them if they're not frozen).

6. **Arabic Content**: All notification titles and bodies are in Arabic; no localization framework required for v1.

---

## Success Criteria from Spec

- SC-001: 100% of eligible contractors with device receive push within 5 min; 100% have in-app record
- SC-002: 99%+ of taps open correct announcement
- SC-003: 100% of outstanding-payment contractors reminded per cycle; 0% of paid contractors
- SC-004: 100% of expiring contractors get reminder with correct date; 0% outside window
- SC-005: Re-run same day → 0 duplicate reminders
- SC-006: Measurable increase in renewal/payment completion post-launch (union-defined baseline)

---

**Next Step**: Run `/speckit-plan` to generate Phase 0 research.md + Phase 1 design artifacts (data-model.md, contracts/, quickstart.md).
