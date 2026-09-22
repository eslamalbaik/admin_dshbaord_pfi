# Research: App Notifications — Announcements & Subscription Reminders

**Date**: 2026-09-22 | **Phase**: 0 (Exploration)

## Objective

Clarify open questions identified in the spec and plan, document timing/idempotency decisions, and establish a foundation for Phase 1 design.

---

## Question 1: Reminder Timing & Cadence

### Open Question
- **Payment Reminder**: Contractors have unpaid/outstanding membership subscriptions. When should reminders start (immediately, X days after due date, during grace period)? How often (daily, weekly, every N days)?
- **Expiry Reminder**: Contractors with membership expiring within a window. What is the window size (7 days, 14 days, 30 days before expiry)?

### Investigation

**Existing Code**: Checked `SendGracePeriodReminders` and `SendRenewalReminders` commands:
- **SendGracePeriodReminders**: Runs daily, targets contractors in a post-expiry grace period (date logic TBD, confirm in code review).
- **SendRenewalReminders**: Runs daily, targets contractors whose membership is expiring soon (window TBD).

**Decision**:
- **Payment Reminder**: Align with existing grace-period logic. Send one reminder per grace-period cycle (likely once per run, idempotent by run date). Window and cadence are defined by the existing `SendGracePeriodReminders` logic.
- **Expiry Reminder**: Align with existing renewal-reminder logic. Window size likely 7–30 days before expiry (confirm in code review); send one reminder per cycle, idempotent by run date.

**Action**: Code review of both commands to extract exact date logic, then document in data-model.md.

---

## Question 2: Idempotency Mechanism

### Open Question
- How do we prevent re-running the same job on the same day from creating duplicate notifications for the same contractor/obligation?

### Investigation

**Constraint**: Spec requires re-running same-day → 0 duplicates (SC-005).

**Options**:
1. **Run-Date Tracking Table**: New table `notification_runs` (job_name, run_date, created_at) — on each job run, check if an entry exists for today; if not, insert one, then proceed with notifications. Mark notifications with the run ID for traceability.
2. **Flag on Contractor**: Add column `last_payment_reminder_date` / `last_expiry_reminder_date` on `contractors` table — check if notification was sent today.
3. **Notification Lookup**: Before creating a notification, query if one with same (contractor_id, type, run_cycle) was created today.

**Decision**: **Option 1 (Run-Date Tracking)** is clearest and most explicit.
- New table: `notification_runs` (id, job_name [enum: grace-period, renewal], run_date, contractor_count, created_at, updated_at)
- Each job starts by checking/inserting a `notification_runs` entry for today.
- Each Notification record links to the run (foreign key) for auditability.
- Query on next run: `WHERE notification_runs.run_date = TODAY AND job_name = ?` to skip contractors already notified.

**Alternative if simpler**: Just check `WHERE created_at >= NOW() - INTERVAL 1 DAY AND contractor_id = ? AND type = ?` before creating, and skip if found. No new table, but slightly messier.

---

## Question 3: Announcement Audience Interpretation

### Open Question
- When publishing an announcement, how do we determine which contractors should be notified?
- Does the Announcement model have an "audience" field (all contractors, category/field segment, manual list)?

### Investigation

**Spec Assumption** (FR-004): "System MUST determine announcement recipients based on the announcement's intended audience (e.g., all active contractors, or a category/audience segment when specified)."

**Code Review Action**: Inspect Announcement model to confirm if an `audience` or `target` field exists.

**Expected Options**:
- If audience field exists: filter eligible contractors by audience before broadcasting.
- If no audience field: broadcast to all active (non-frozen, verified) contractors.

**Decision (Provisional)**: Assume Announcement model has an audience field; if not, default to all active contractors. Confirm in code review of Announcement model and migration history.

---

## Question 4: Frozen Contractor Eligibility

### Open Question
- The spec mentions (FR-011) that notification eligibility respects existing account-status rules. Does that mean:
  - Frozen contractors receive NO notifications (not even in-app)?
  - Or just that frozen contractors don't get push (but still get in-app)?

### Investigation

**Existing Middleware**: `EnsureContractorIsActive` (referenced in CLAUDE.md) checks `is_frozen` and force-revokes tokens if true. This blocks ALL contractor-portal requests.

**Spec Context**: 
- "ineligible accounts are not notified" (FR-011).
- "Frozen contractor account — When the reminder run executes, Then the reminder behavior follows the same eligibility rules the union applies to other contractor notifications" (US2, scenario 4).

**Decision**: 
- **Frozen contractors receive NO notifications** (neither push nor in-app in-app records).
- This aligns with the middleware (frozen = account inaccessible).
- When building eligible contractor lists, filter out `is_frozen = true`.
- Suspended contractors (different from frozen) may still receive reminders if the renewal blocker allows, per existing business logic (see CLAUDE.md).

---

## Question 5: Existing Notification Table Schema

### Open Question
- Does a `notifications` table already exist? If so, what fields does it have?

### Investigation

**Code Review Action**: Check `database/migrations/` for existing Notification-related migrations and inspect `app/Models/Notification.php`.

**Expected Schema** (from CLAUDE.md context):
- `id`, `contractor_id`, `type` (enum), `title`, `body`, `reference_id` (for links), `read_at`, `created_at`, `updated_at`

**Decision**: If table exists and is used by existing notification flows, extend it. If not, create it. Add a `run_id` field (nullable, foreign key to `notification_runs`) for idempotency tracking.

---

## Question 6: Announcement Event Trigger

### Open Question
- When is the "announcement published" event fired? Does it exist already?

### Investigation

**Code Review Action**: Inspect AnnouncementController to see if it fires an event on publish.

**Expected Pattern**: `dispatch(new AnnouncementPublished($announcement))` when status transitions from draft to published.

**Decision**: If event doesn't exist, create `AnnouncementPublished` event. If it does, reuse it. Either way, attach a listener `SendAnnouncementNotifications` to handle broadcast.

---

## Question 7: Device Token Registration Flow

### Open Question
- How do contractors register their FCM device tokens? Does the endpoint exist?

### Investigation

**Code Review Action**: Grep for FCM, device token, or push-related routes in `routes/api.php` and controllers.

**Expected Pattern** (mobile app convention): 
- `POST contractor/device-tokens` (register)
- Device tokens stored on `contractors` table or separate `contractor_device_tokens` pivot table.

**Decision**: Assume device token registration already exists (common in mobile apps). If missing, add it. Don't block notification delivery on device token availability; missing token = push skipped, in-app persisted.

---

## Question 8: Reverb vs. FCM for Admin Notifications

### Open Question
- The spec is about **contractor** notifications (mobile/portal). Admin notifications (contractor activation, payment submission) already use Reverb. Should we reuse Reverb for contractors, or stick with FCM?

### Investigation

**CLAUDE.md Context**: 
- Reverb broadcasts to admin browsers in real-time.
- FCM broadcasts to contractor phones asynchronously (Firebase Cloud Messaging).
- Contractor notifications should be durable and work offline → FCM + DB is correct.
- Admin notifications are real-time browser alerts → Reverb is correct.

**Decision**: Keep the two separate. 
- Contractors: FCM (push) + DB (in-app).
- Admins: Reverb (live broadcast) + DB (optional fallback).

---

## Summary: Key Decisions for Phase 1 Design

| Question | Decision | Rationale |
|----------|----------|-----------|
| Payment Reminder Timing | Align with existing `SendGracePeriodReminders` logic | Reuse existing infrastructure, avoid duplicating date logic |
| Expiry Reminder Window | Align with existing `SendRenewalReminders` logic | Same |
| Idempotency | Run-date tracking table (`notification_runs`) | Clear, auditable, prevents same-day duplicates |
| Announcement Audience | Query audience field on Announcement; default to all active contractors | Flexible for future segmentation |
| Frozen Contractors | Exclude from all notifications (push + in-app) | Aligns with existing middleware (frozen = no portal access) |
| Notification Schema | Extend existing table (if present) or create new with run_id field | Maintain consistency with existing patterns |
| Event Trigger | Create or reuse `AnnouncementPublished` event + listener | Event-driven architecture, decoupled from controller |
| Device Tokens | Assume existing endpoint; graceful missing-token handling | Mobile app convention; push is best-effort anyway |
| Contractor vs. Admin | Keep FCM (contractors) and Reverb (admins) separate | Different delivery semantics and use cases |

---

## Next Steps

Phase 1 Design will:
1. Formalize data-model.md with exact Notification table schema and migration.
2. Document notification-push.md with FCM payload contract.
3. Create quickstart.md with manual test scenarios.
4. Code review (separate from this task) will confirm assumptions above.
