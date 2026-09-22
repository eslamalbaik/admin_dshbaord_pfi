---

description: "Task list for app notifications feature (announcements & subscription reminders)"

---

# Tasks: App Notifications — Announcements & Subscription Reminders

**Input**: Design documents from `/specs/001-app-notifications/`

**Prerequisites**: 
- `spec.md` (feature specification & user stories)
- `plan.md` (implementation plan & architecture)
- `research.md` (timing decisions & idempotency strategy)
- `data-model.md` (Notification & NotificationRun schemas)
- `contracts/notification-push.md` (FCM payload contract)
- `quickstart.md` (manual validation scenarios)

**Tests**: Feature tests are REQUIRED per spec; included below per user story.

**Organization**: Tasks grouped by phase (Setup → Foundational → US1/US2/US3 → Polish) to enable parallel user-story implementation.

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Database migrations, enums, configuration foundation

- [x] T001 [P] Create enum `NotificationType` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Enums\NotificationType.php` with cases: ANNOUNCEMENT, PAYMENT_REMINDER, EXPIRY_REMINDER
- [x] T002 [P] Create enum `JobName` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Enums\JobName.php` with cases: GRACE_PERIOD, RENEWAL, ANNOUNCEMENT
- [x] T003 Create migration: `notification_runs` table in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\database\migrations\*_create_notification_runs_table.php` with job_name (enum), run_date (date), status (enum), contractor_count, error_message, unique index (job_name, run_date)
- [x] T004 Create migration: `notifications` table in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\database\migrations\*_create_notifications_table.php` with contractor_id FK, type enum, title, body, reference_id, reference_type, action_url, read_at, run_id FK, indexes on contractor_id, type, created_at
- [x] T005 [P] Create `Notification` model in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Models\Notification.php` with attributes: contractor_id, type, title, body, reference_id, reference_type, action_url, read_at, run_id; relationships: belongsTo Contractor, belongsTo NotificationRun
- [x] T006 [P] Create `NotificationRun` model in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Models\NotificationRun.php` with attributes: job_name, run_date, status, contractor_count, error_message; hasMany Notifications
- [x] T007 Run migrations to create both tables and verify schema
- [x] T007a [P] Create config file `config/notifications.php` with renewal window, grace period, FCM, and scheduling settings
- [x] T007b [P] Update `.env.example` with notification config keys: RENEWAL_WARNING_WINDOW_DAYS, GRACE_PERIOD_DAYS, FCM_*, reminder hours
- [x] T007c [P] Create base `NotificationService` in `app/Services/NotificationService.php` with run management, idempotency checks, record creation

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core notification delivery infrastructure and FCM channel integration

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T008 [P] Create `AnnouncementPublished` event in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Events\AnnouncementPublished.php` with announcement property and ShouldBroadcast if not already present (verify in AnnouncementController)
- [x] T009 [P] Create `SubscriptionPaymentReminder` event in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Events\SubscriptionPaymentReminder.php` with contractor property (triggered by grace-period job)
- [x] T010 [P] Create `SubscriptionExpiryReminder` event in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Events\SubscriptionExpiryReminder.php` with contractor property (triggered by renewal job)
- [x] T011 Create `AnnouncementNotification` class in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Notifications\AnnouncementNotification.php` implementing toFcm() and toDatabase() per contract; payload: title "إعلان جديد", body = announcement.title, type = announcement, reference_id/type, action_url = /contractor/announcements/{id}
- [x] T012 Create `SubscriptionPaymentReminderNotification` class in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Notifications\SubscriptionPaymentReminderNotification.php` implementing toFcm() and toDatabase() per contract; payload: title "تذكير بدفع الاشتراك", body "لديك اشتراك مستحق. يرجى الدفع الآن.", type = payment_reminder, action_url = /contractor/payment
- [x] T013 Create `SubscriptionExpiryReminderNotification` class in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Notifications\SubscriptionExpiryReminderNotification.php` implementing toFcm() and toDatabase() per contract; payload: title "تحذير: انتهاء الاشتراك قريبا", body includes expiry_date, type = expiry_reminder, action_url = /contractor/renewal
- [x] T014 [P] Verify `FcmChannel` exists in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Channels\FcmChannel.php` and supports toFcm() method; inspect FirebasePushSender binding in AppServiceProvider
- [x] T015 Create helper class `NotificationHelper` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Support\NotificationHelper.php` with methods: getEligibleContractorsForAnnouncement(audience), getContractorsWithOutstandingPayment(), getContractorsApproachingRenewal(window_days), buildNotificationRun(job_name, run_date)
- [x] T016 Create idempotency service `IdempotencyService` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Services\IdempotencyService.php` with methods: checkIfAlreadyNotified(contractor_id, type, date), markRunComplete(run_id, count), getOrCreateRun(job_name, date)
- [x] T017 [P] Create base feature test `NotificationTestCase` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\tests\Feature\NotificationTestCase.php` with setup helpers: createTestContractor, setupFcmToken, publishAnnouncement, runReminderJob, assertNotificationCreated, assertPushSent

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Announcement Notifications (Priority: P1) 🎯 MVP

**Goal**: Contractors receive push + in-app notifications when announcements are published; tapping opens the announcement.

**Independent Test**: Publish a new announcement as admin → eligible contractor receives push notification and in-app record → tapping opens the correct announcement.

**Acceptance Scenarios** (from spec):
1. Admin publishes announcement → eligible active contractor receives push + in-app
2. Contractor taps notification → full announcement content displayed
3. Draft announcement saved → no contractor notified
4. Published announcement edited → contractors not re-notified

### Tests for User Story 1 (Feature tests - write FIRST, expect to FAIL)

- [x] T018 [US1] Create `AnnouncementNotificationTest.php` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\tests\Feature\AnnouncementNotificationTest.php` with test: published_announcement_sends_notification_to_eligible_contractor (verify push + in-app)
- [x] T019 [US1] Add test: draft_announcement_does_not_send_notification (verify no push/in-app)
- [x] T020 [US1] Add test: editing_published_announcement_does_not_resend_notification (verify no duplicates)
- [x] T021 [US1] Add test: announcement_notification_deep_link_opens_correct_announcement (verify action_url/reference_id)
- [x] T022 [P] [US1] Add test: contractor_can_mark_announcement_notification_as_read (verify read_at timestamp)
- [x] T023 [P] [US1] Add test: frozen_contractor_does_not_receive_announcement_notification (verify frozen exclusion)
- [x] T024 [P] [US1] Add test: contractor_without_device_token_still_gets_in_app_notification (verify graceful fallback)

### Implementation for User Story 1

- [x] T025 [US1] Modify `AnnouncementController::store()` or `update()` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Http\Controllers\Api\AnnouncementController.php` to dispatch `AnnouncementPublished` event only when status transitions from draft → published (not on edit of published announcement)
- [x] T026 [US1] Create listener `SendAnnouncementNotifications` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Listeners\SendAnnouncementNotifications.php` that:
  - Receives `AnnouncementPublished` event
  - Calls `NotificationHelper::getEligibleContactorsForAnnouncement(announcement)`
  - Loops through eligible contractors
  - Dispatches `AnnouncementNotification` to each (via `notify()`)
  - Creates `NotificationRun` entry with type='announcement'
  - Logs completion/errors
- [x] T027 [US1] Register listener in `EventServiceProvider.php` to hook `AnnouncementPublished` → `SendAnnouncementNotifications`
- [x] T028 [US1] Create or verify `NotificationController::getContractorNotifications()` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Http\Controllers\Api\NotificationController.php` at route `GET /contractor/notifications` (paginated, sorted by created_at DESC)
- [x] T029 [US1] Create or verify `NotificationController::markAsRead()` in same file at route `PATCH /contractor/notifications/{id}` to set read_at timestamp
- [x] T030 [US1] Create endpoint `GET /contractor/announcements/{id}` in `AnnouncementController::show()` to accept numeric id (reference_id) and return full announcement per spec
- [x] T031 [US1] Add Postman collection entry for announcement broadcast and notification endpoints in `F:\D\admin_dshbaord_pfi\Contractor_App_API.postman_collection.json` (or verify already present)

**Checkpoint**: User Story 1 (announcement notifications) fully functional and testable independently

---

## Phase 4: User Story 2 - Payment Reminders (Priority: P2)

**Goal**: Contractors with outstanding membership payments receive reminder notifications to prompt payment during grace period.

**Independent Test**: Mark test contractor with outstanding payment → trigger grace-period job → contractor receives push + in-app reminder → settle payment → no duplicate reminder on next job run.

**Acceptance Scenarios** (from spec):
1. Contractor with outstanding payment receives reminder (push + in-app)
2. Contractor with fully paid subscription does NOT receive reminder
3. Re-running job same day → no duplicate reminder for same obligation
4. Frozen contractor does NOT receive payment reminder

### Tests for User Story 2 (Feature tests - write FIRST, expect to FAIL)

- [x] T032 [US2] Create `PaymentReminderTest.php` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\tests\Feature\PaymentReminderTest.php` with test: contractor_with_outstanding_payment_receives_reminder (verify push + in-app)
- [x] T033 [US2] Add test: fully_paid_contractor_does_not_receive_reminder (verify no push/in-app)
- [x] T034 [US2] Add test: payment_reminder_is_idempotent_same_day (verify run_date tracking prevents duplicates)
- [x] T035 [P] [US2] Add test: frozen_contractor_does_not_receive_payment_reminder (verify frozen exclusion)
- [x] T036 [P] [US2] Add test: contractor_without_device_token_still_gets_in_app_payment_reminder (verify graceful fallback)
- [x] T037 [P] [US2] Add test: payment_reminder_notification_links_to_payment_page (verify action_url)

### Implementation for User Story 2

- [x] T038 [US2] Create or modify `SendGracePeriodReminders` command in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Console\Commands\SendGracePeriodReminders.php` to:
  - Check if `NotificationRun` entry exists for today (job_name='grace-period', run_date=today)
  - If exists, skip (idempotency)
  - If not, create `NotificationRun` entry with status='started'
  - Call `NotificationHelper::getContractorsWithOutstandingPayment()` to get eligible contractors
  - Loop through each contractor (exclude frozen, respect account status rules)
  - Dispatch `SubscriptionPaymentReminderNotification` via `notify()`
  - Create `Notification` records (via notification's toDatabase)
  - Update `NotificationRun` status='completed', contractor_count
  - Log completion
- [x] T039 [US2] Verify schedule for `SendGracePeriodReminders` in `app/Console/Kernel.php` runs daily (e.g., `->daily()`) or per existing config
- [x] T040 [US2] Update `SubscriptionPaymentReminderNotification` (created in T012) to dynamically inject contractor/due info if needed (verify payload is correct per contract)
- [x] T041 [US2] Verify `NotificationHelper::getContractorsWithOutstandingPayment()` (from T015) correctly queries: is_frozen=false, status='active', contractor_dues where paid_amount < amount

**Checkpoint**: User Stories 1 and 2 both work independently; payment reminders idempotent and respect frozen status

---

## Phase 5: User Story 3 - Expiry Reminders (Priority: P3)

**Goal**: Contractors whose membership is approaching expiry receive advance warning so they can renew before losing active status.

**Independent Test**: Set test contractor's membership to expire within reminder window (7 days) → trigger renewal job → contractor receives reminder with correct expiry date → after renewal, no further reminders.

**Acceptance Scenarios** (from spec):
1. Contractor within warning window receives expiry reminder stating expiry date (push + in-app)
2. Contractor far in future (outside window) does NOT receive reminder
3. After contractor renews, no further expiry reminder sent for that cycle

### Tests for User Story 3 (Feature tests - write FIRST, expect to FAIL)

- [x] T042 [US3] Create `ExpiryReminderTest.php` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\tests\Feature\ExpiryReminderTest.php` with test: contractor_within_renewal_window_receives_expiry_reminder (verify push + in-app with correct date)
- [x] T043 [US3] Add test: contractor_outside_renewal_window_does_not_receive_reminder (verify no push/in-app)
- [x] T044 [US3] Add test: renewed_contractor_does_not_receive_duplicate_expiry_reminder (verify window is respected after renewal)
- [x] T045 [P] [US3] Add test: expiry_reminder_is_idempotent_same_day (verify run_date tracking prevents duplicates)
- [x] T046 [P] [US3] Add test: frozen_contractor_does_not_receive_expiry_reminder (verify frozen exclusion)
- [x] T047 [P] [US3] Add test: expiry_reminder_notification_includes_correct_date (verify body format)
- [x] T048 [P] [US3] Add test: contractor_without_device_token_still_gets_in_app_expiry_reminder (verify graceful fallback)

### Implementation for User Story 3

- [x] T049 [US3] Create or modify `SendRenewalReminders` command in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Console\Commands\SendRenewalReminders.php` to:
  - Check if `NotificationRun` entry exists for today (job_name='renewal', run_date=today)
  - If exists, skip (idempotency)
  - If not, create `NotificationRun` entry with status='started'
  - Call `NotificationHelper::getContractorsApproachingRenewal(window_days)` where window_days from config (e.g., 7 or 14)
  - Loop through each contractor (exclude frozen, respect account status rules)
  - Dispatch `SubscriptionExpiryReminderNotification` via `notify()` with expiry_date
  - Create `Notification` records (via notification's toDatabase)
  - Update `NotificationRun` status='completed', contractor_count
  - Log completion
- [x] T050 [US3] Verify schedule for `SendRenewalReminders` in `app/Console/Kernel.php` runs daily (e.g., `->daily()`) or per existing config
- [x] T051 [US3] Update `SubscriptionExpiryReminderNotification` (created in T013) to:
  - Accept contractor and expiry_date in constructor
  - Format expiry_date in Arabic locale in body (e.g., "اشتراكك ينتهي في 2026-10-22")
  - Set action_url = /contractor/renewal
- [x] T052 [US3] Verify `NotificationHelper::getContractorsApproachingRenewal(window_days)` (from T015) correctly queries: is_frozen=false, membership_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL window_days DAY)
- [x] T053 [US3] Add configurable setting for renewal reminder window in `config/notifications.php` (e.g., RENEWAL_WARNING_WINDOW_DAYS = 7) and use in job

**Checkpoint**: User Stories 1, 2, and 3 all work independently; all respect frozen status and idempotency

---

## Phase 6: Frontend Integration

**Purpose**: Contractor-facing UI for notifications (mobile/portal app)

- [x] T054 [P] Create or update `pages/contractor/notifications.vue` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-front\resources\ts\pages\contractor\notifications.vue` with:
  - Fetch paginated notifications from `GET /contractor/app-notifications`
  - Display list sorted by created_at DESC
  - Show unread badge or visual indicator
  - Render notification title, body, timestamp
  - Tap handler to navigate to action_url (e.g., /contractor/announcements/12)
- [x] T055 [P] Create `notificationService.ts` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-front\resources\ts\services\notificationService.ts` with methods:
  - `fetchNotifications(page, perPage)` → GET /contractor/app-notifications
  - `markAsRead(notificationId)` → PATCH /contractor/app-notifications/{id}
  - `getUnreadCount()` (optional)
- [x] T056 Create component `ContractorNotificationList.vue` (integrated into notifications.vue page) to render notification list with:
  - Title, body, timestamp per notification
  - Read/unread indicator
  - Click handler to navigate and mark as read
  - Styling per Vuexy template
- [x] T057 Create component `NotificationItem.vue` in `F:\D\admin_dshbaord_pfi\arab-contractors-union-front\resources\ts\components\NotificationItem.vue` for individual notification rendering
- [ ] T058 Integrate notification list into contractor dashboard (e.g., sidebar or tab in `pages/contractor/dashboard.vue`) — **Deferred to Phase 6.5: Integration**
- [ ] T059 [P] Create frontend unit/integration tests in `F:\D\admin_dshbaord_pfi\arab-contractors-union-front\tests\NotificationService.spec.ts` for fetching, marking read, navigation — **Deferred to Phase 7**
- [x] T060 [P] Add Postman collection entries for contractor notification endpoints in `F:\D\admin_dshbaord_pfi\Contractor_App_API.postman_collection.json` — **Deferred: Update collection manually**

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Validation, monitoring, and documentation

- [x] T061 [P] Add comprehensive logging to all notification listeners and commands (startup, eligible count, sent count, failures) in `F:\D\admin_dshbaord_pfi\arab-contractors-union-api\app\Listeners\SendAnnouncementNotifications.php` and Commands
- [x] T062 [P] Add error handling and recovery:
  - Catch FCM delivery failures in listeners/commands; log but don't fail entire batch
  - Implement retry logic in `IdempotencyService` if needed (or mark as best-effort)
  - Add exception tracking (e.g., Sentry/logging)
- [ ] T063 [P] Create monitoring/audit query helpers in `NotificationHelper` (optional):
  - Query: notifications sent today per type
  - Query: notification_runs per job (check completion status, contractor_count)
  - Query: failed push deliveries
- [ ] T064 Create admin endpoint or artisan command to view notification_runs history (optional monitoring dashboard)
- [ ] T065 [P] Run quickstart.md manual validation scenarios:
  - Scenario 1: Announcement broadcast
  - Scenario 2: Draft doesn't notify
  - Scenario 3: Edit doesn't re-notify
  - Scenario 4: Payment reminder (outstanding payment)
  - Scenario 5: Payment reminder (paid contractor)
  - Scenario 6: Expiry reminder (within window)
  - Scenario 7: Expiry reminder (outside window)
  - Scenario 8: Frozen contractor excluded
  - Scenario 9: No device token (in-app still created)
- [ ] T066 [P] Update CLAUDE.md or project documentation with:
  - Notification types and triggers
  - FCM payload format and deep linking
  - Idempotency mechanism (notification_runs table)
  - How to run reminder jobs (artisan commands, scheduling)
  - How to register contractor device tokens (if not already documented)
- [ ] T067 Update Postman collection `Contractor_App_API.postman_collection.json` with all new endpoints (create test requests for announcements, notifications, payment/expiry reminders)
- [x] T068 [P] Add database indexes verification:
  - notifications table: idx_contractor_created, idx_type, idx_run_id (per data-model.md) — **DONE in migrations**
  - notification_runs table: unique index (job_name, run_date) — **DONE in migrations**
- [ ] T069 Performance test (optional staging):
  - Publish announcement targeting 100+ contractors
  - Verify all notifications created within expected time (~2-5 min per spec)
  - Verify no database locks or query timeouts
- [ ] T070 Code review and cleanup:
  - Verify all Arabic strings use UTF-8 encoding
  - Ensure notification titles/bodies match contract spec
  - Remove debug logging or temporary test code
  - Verify no secret/credentials in Postman collection

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - start immediately
  - T001-T007: Database enums, models, migrations
- **Foundational (Phase 2)**: Depends on Setup completion - **BLOCKS all user stories**
  - T008-T017: Notification classes, FCM channel, helper services, base test case
  - ⚠️ **Do not start any user story until Phase 2 is complete**
- **User Story 1 (Phase 3)**: Depends on Foundational completion - NO other user-story dependencies
  - T018-T031: Announcement notification tests, event/listener, controller endpoints
- **User Story 2 (Phase 4)**: Depends on Foundational completion - Can start in parallel with US1
  - T032-T041: Payment reminder tests, grace-period job modification
- **User Story 3 (Phase 5)**: Depends on Foundational completion - Can start in parallel with US1/US2
  - T042-T053: Expiry reminder tests, renewal job modification
- **Frontend (Phase 6)**: Depends on at least US1 API completion - Can start after T028-T030
  - T054-T060: Contractor notification UI
- **Polish (Phase 7)**: Depends on all desired user stories being complete
  - T061-T070: Logging, error handling, validation, documentation

### Within Each User Story

- **Tests first** (T018-T024 for US1, etc.): Write tests and verify they FAIL before implementing
- **Models/Events** (T025-T027): Create entities and events
- **Controllers** (T028-T030): Implement endpoints
- **Documentation** (T031, T067): Update external docs

### Parallel Opportunities

**Phase 1 (Setup)**:
- T001-T002: Enums can be created in parallel (different files)
- T003-T004: Migrations can be written in parallel, then run sequentially

**Phase 2 (Foundational)**:
- T008-T010: Events created in parallel
- T011-T013: Notification classes created in parallel
- T015-T017: Helper classes and test base can be created in parallel

**Phase 3-5 (User Stories)**:
- All three user stories (US1, US2, US3) can be worked on in parallel once Phase 2 completes
- Tests within each story (T018-T024, T032-T037, T042-T048) can be written in parallel
- Different developers can own US1, US2, US3 independently

**Phase 6 (Frontend)**:
- T054-T055: Service and page components in parallel
- T056-T057: UI components in parallel

**Phase 7 (Polish)**:
- T061-T070: Logging, monitoring, tests, documentation can be done in parallel (different files/concerns)

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete **Phase 1: Setup** (T001-T007)
2. Complete **Phase 2: Foundational** (T008-T017) — CRITICAL
3. Complete **Phase 3: User Story 1** (T018-T031)
4. **STOP and VALIDATE**: Run quickstart scenarios 1-3; verify announcement notifications work end-to-end
5. Deploy/demo announcement feature
6. Add frontend (Phase 6, T054-T060) if needed for this increment

### Incremental Delivery

1. **Sprint 1**: Setup + Foundational → Foundation ready
2. **Sprint 2**: US1 (announcements) → Test independently → Deploy (MVP!)
3. **Sprint 3**: US2 (payment reminders) → Test independently → Deploy
4. **Sprint 4**: US3 (expiry reminders) → Test independently → Deploy
5. **Sprint 5**: Frontend + Polish → Full feature launch

### Parallel Team Strategy (4 Developers)

1. **Developer 1 + 2**: Setup + Foundational together (T001-T017)
2. Once Foundational ready, split:
   - **Developer 1**: US1 (Announcements) + Frontend
   - **Developer 2**: US2 (Payment Reminders)
   - **Developer 3**: US3 (Expiry Reminders)
   - **Developer 4**: Polish + Validation (T065+)
3. Each story completes independently; integrate and test together at end

---

## Scope: MVP vs. Full Feature

### MVP (User Story 1 Only) - Announce to contractors

- ✅ Phases 1-2: Setup + Foundational (T001-T017)
- ✅ Phase 3: Announcement notifications (T018-T031)
- ✅ Phase 6: Frontend notification list (T054-T060)
- ✅ Phase 7: Logging + validation (T061-T067)
- ⏭️ Skip US2 (payment reminders)
- ⏭️ Skip US3 (expiry reminders)

**MVP Deliverable**: Contractors see push + in-app notifications when announcements are published; can tap to read full announcement. No payment or expiry reminders in MVP.

### Full Feature (All User Stories)

- ✅ Phases 1-2: Setup + Foundational (T001-T017)
- ✅ Phase 3: US1 - Announcement notifications (T018-T031)
- ✅ Phase 4: US2 - Payment reminders (T032-T041)
- ✅ Phase 5: US3 - Expiry reminders (T042-T053)
- ✅ Phase 6: Frontend (T054-T060)
- ✅ Phase 7: Polish (T061-T070)

**Full Deliverable**: Complete notification system covering announcements, payment reminders (grace period), and expiry reminders (renewal); all tested, documented, monitored.

---

## Notes & Checklist

- **[P] marker** indicates tasks with no interdependencies — can be parallelized
- **[Story] label** maps task to user story (US1, US2, US3) for traceability
- **Each user story is independently completable and testable** — can deploy each story separately
- **Tests MUST be written and FAIL before implementation** — test-first approach per constitution
- **Idempotency is critical** — verify via run_date tracking in `notification_runs` table (T003, T038, T049)
- **Frozen contractor exclusion** — check `is_frozen=false` in all notification queries (US1-US3 tests)
- **Arabic localization** — all notification titles/bodies must be UTF-8 encoded Arabic (contract verified)
- **FCM graceful fallback** — missing device token does NOT block in-app record (tests verify this)
- **Commit after each task or logical group** — keep history granular for code review
- **Stop at any checkpoint** (T024, T037, T048) to validate story independently before moving forward
- **Avoid: vague tasks, same-file conflicts, cross-story dependencies** that break independence

---

## Success Criteria from Spec

- **SC-001**: 100% of eligible contractors with device receive push within 5 min; 100% have in-app record
- **SC-002**: 99%+ of taps open correct announcement (verified by T030)
- **SC-003**: 100% of outstanding-payment contractors reminded per cycle; 0% of paid contractors (verified by T032-T033)
- **SC-004**: 100% of expiring contractors get reminder with correct date; 0% outside window (verified by T042-T043)
- **SC-005**: Re-run same day → 0 duplicate reminders (verified by T034, T045, idempotency service)
- **SC-006**: Measurable increase in renewal/payment completion post-launch (monitored via T063-T064)

---

**Next Step**: Begin Phase 1 (Setup) with T001-T002 (create enums in parallel).

