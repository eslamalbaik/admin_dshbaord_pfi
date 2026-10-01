# Quickstart: App Notifications — Manual Testing & Validation

**Date**: 2026-09-22 | **Phase**: 1 (Design)

**Purpose**: Step-by-step manual test scenarios to validate each notification type before feature launch. These serve as acceptance tests and can be automated later.

---

## Prerequisites

- Test environment with Laravel backend running (`http://localhost:8000` locally, or staging VPS).
- Admin account with permission to publish announcements.
- Contractor test accounts with mobile app installed or web portal access.
- Real Android/iOS device (or emulator) with Firebase Cloud Messaging set up.
- Contractor device registered with a valid FCM token.

---

## Scenario 1: Announcement Broadcast

**Goal**: Verify that publishing a new announcement triggers push notifications to eligible contractors and creates in-app records.

### Setup

1. Log in as admin to the dashboard.
2. Identify a test contractor account (e.g., `membership_number: 1_g`) that is:
   - Active (`status = 'active'`)
   - Not frozen (`is_frozen = FALSE`)
   - Verified
   - Has a registered FCM token (mobile app installed)

### Steps

1. **Admin publishes an announcement**:
   - Navigate to: Admin Dashboard → Announcements → Create New.
   - Fill in:
     - Title: "اختبار الإشعارات"
     - Body: "هذا اختبار لميزة الإشعارات الجديدة"
     - Audience: "All Active Contractors" (or leave default)
     - Status: "Published"
   - Click "Save & Publish".
   - Note the announcement ID (e.g., `#123`).

2. **Verify push notification on contractor device**:
   - Within 5 minutes, the contractor's device should receive a push notification.
   - **Expected notification**:
     - Title: "إعلان جديد"
     - Body: "اختبار الإشعارات" (the announcement title)
   - Tap the notification.
   - **Expected behavior**: Opens the announcement details in the contractor's app/portal.

3. **Verify in-app notification record**:
   - Log in as the test contractor to the mobile app or web portal.
   - Navigate to: Notifications (or Messages/Inbox).
   - **Expected**: A new notification appears at the top of the list.
     - Title: "إعلان جديد"
     - Body: "اختبار الإشعارات"
     - Timestamp: Just now (or "منذ الآن")
     - Mark as read / tap to open: Opens the announcement.

### Acceptance Criteria

- [ ] Push notification arrives within 5 minutes of publish.
- [ ] Notification title and body are correct and in Arabic.
- [ ] Tapping notification opens the correct announcement.
- [ ] In-app notification list shows the record and allows viewing/reading.
- [ ] Notification is marked as unread until contractor taps it (read_at is null initially).

---

## Scenario 2: Announcement Draft Does Not Notify

**Goal**: Verify that saving an announcement as draft does NOT trigger notifications.

### Steps

1. **Admin creates a draft announcement**:
   - Navigate to: Admin Dashboard → Announcements → Create New.
   - Fill in:
     - Title: "مسودة اختبار"
     - Body: "هذا الإعلان في حالة مسودة"
     - Status: "Draft"
   - Click "Save".

2. **Verify NO notification is sent**:
   - Wait 2–3 minutes.
   - Check the contractor device: no notification should appear.
   - Check in-app notification list: no new notification should be added.

3. **Publish the draft**:
   - Admin returns to the announcement and changes Status from "Draft" to "Published".
   - Click "Save & Publish".

4. **Verify notification NOW arrives**:
   - Within 5 minutes, contractor device should receive a push notification for the now-published announcement.
   - In-app notification list should show the record.

### Acceptance Criteria

- [ ] Saving as draft produces no notification.
- [ ] Publishing draft (transition from draft → published) triggers notification.
- [ ] Re-editing a published announcement (no status change) does NOT re-notify.

---

## Scenario 3: Announcement Edit Does Not Re-Notify

**Goal**: Verify that editing an already-published announcement does NOT re-notify contractors.

### Setup

- Use the announcement created in Scenario 1.

### Steps

1. **Admin edits the published announcement**:
   - Navigate to: Admin Dashboard → Announcements → [existing announcement from Scenario 1].
   - Edit the body text (e.g., add "تم التحديث" at the end).
   - Save.

2. **Verify NO re-notification is sent**:
   - Wait 2–3 minutes.
   - Contractor device should NOT receive another notification.
   - Check in-app list: only one notification for this announcement (from Scenario 1) should exist; no duplicate.

### Acceptance Criteria

- [ ] Editing published announcement produces no new push notification.
- [ ] In-app notification list does not show duplicate records for the same announcement.

---

## Scenario 4: Payment Reminder — Outstanding Subscription

**Goal**: Verify that contractors with outstanding membership payments receive a reminder.

### Setup

1. Identify a test contractor account (e.g., `1_g`).
2. Mark the contractor as having an outstanding payment:
   - Method A (via admin panel): Admin Dashboard → Contractors → [contractor] → Set Outstanding Payment / Dues.
   - Method B (via DB directly, for testing): `INSERT INTO contractor_dues (contractor_id, amount, paid_amount, ...) VALUES (1, 1000, 0, ...)`
3. Ensure the contractor is active, not frozen, and has a registered FCM token.

### Steps

1. **Trigger the payment reminder job**:
   - Run: `php artisan send:grace-period-reminders` (or appropriate command name per Phase 2 tasks).
   - Or (on staging): Navigate to Admin Dashboard → Jobs → Run Reminder Job → Select "Payment Reminder".

2. **Verify push notification on contractor device**:
   - Within 2–5 minutes, contractor device should receive a push notification.
   - **Expected notification**:
     - Title: "تذكير بدفع الاشتراك"
     - Body: "لديك اشتراك مستحق. يرجى الدفع الآن."
   - Tap the notification.
   - **Expected behavior**: Opens the payment page / dues section in the app.

3. **Verify in-app notification record**:
   - Log in as contractor.
   - Navigate to: Notifications.
   - **Expected**: New notification appears.
     - Title: "تذكير بدفع الاشتراك"
     - Body: "لديك اشتراك مستحق. يرجى الدفع الآن."

4. **Test idempotency**:
   - Run the reminder job again immediately (same day).
   - **Expected**: No second notification is sent to the same contractor.
   - Check in-app list: still only one payment reminder for today.

### Acceptance Criteria

- [ ] Payment reminder notification arrives after job runs.
- [ ] Notification is in Arabic and references payment obligation.
- [ ] Tapping notification opens payment/dues section.
- [ ] Running job twice on same day produces 0 duplicate notifications (SC-005).

---

## Scenario 5: Payment Reminder — Paid Contractor

**Goal**: Verify that contractors with NO outstanding payment do NOT receive a reminder.

### Setup

1. Identify a test contractor account with fully paid subscription.
2. Ensure the contractor is active and has an FCM token.

### Steps

1. **Trigger the payment reminder job**:
   - Run: `php artisan send:grace-period-reminders`.

2. **Verify NO notification is sent**:
   - Wait 2–3 minutes.
   - Contractor device should NOT receive a payment reminder notification.
   - Check in-app list: no payment reminder should be added.

### Acceptance Criteria

- [ ] Contractors with no outstanding payment receive no reminder.
- [ ] Job completes successfully without errors.

---

## Scenario 6: Expiry Reminder — Membership Expiring Soon

**Goal**: Verify that contractors whose membership is approaching expiry receive a reminder.

### Setup

1. Identify or create a test contractor account.
2. Set the contractor's membership expiry date to **within the reminder window** (e.g., 7 days from today):
   - `membership_expires_at = DATE_ADD(NOW(), INTERVAL 5 DAY)`.
3. Ensure the contractor is active, not frozen, and has an FCM token.

### Steps

1. **Trigger the expiry reminder job**:
   - Run: `php artisan send:renewal-reminders` (or appropriate command name per Phase 2 tasks).

2. **Verify push notification on contractor device**:
   - Within 2–5 minutes, contractor device should receive a push notification.
   - **Expected notification**:
     - Title: "تحذير: انتهاء الاشتراك قريبا"
     - Body: "اشتراكك ينتهي في [date]. يرجى التجديد الآن."
   - Tap the notification.
   - **Expected behavior**: Opens the renewal page in the app.

3. **Verify in-app notification record**:
   - Log in as contractor.
   - Navigate to: Notifications.
   - **Expected**: New notification appears with expiry date.

4. **Verify contractor can renew**:
   - Contractor processes renewal (submit payment).
   - `membership_expires_at` is updated to a future date.

5. **Test no re-notification after renewal**:
   - Run the expiry reminder job again (next day or later).
   - **Expected**: No expiry reminder is sent to this contractor (already renewed).

### Acceptance Criteria

- [ ] Contractor within warning window receives expiry reminder notification.
- [ ] Notification includes the correct expiry date (not a generic date).
- [ ] Tapping notification opens renewal page.
- [ ] After renewal, no further expiry reminders are sent.

---

## Scenario 7: Expiry Reminder — Membership Far in Future

**Goal**: Verify that contractors whose membership is far in the future do NOT receive an expiry reminder.

### Setup

1. Identify a test contractor account with membership expiring 60+ days from today.

### Steps

1. **Trigger the expiry reminder job**:
   - Run: `php artisan send:renewal-reminders`.

2. **Verify NO notification is sent**:
   - Wait 2–3 minutes.
   - Contractor device should NOT receive an expiry reminder.
   - Check in-app list: no expiry reminder notification.

### Acceptance Criteria

- [ ] Contractors outside the reminder window receive no notification.
- [ ] Job completes successfully.

---

## Scenario 8: Frozen Contractor — No Notifications

**Goal**: Verify that frozen contractors do NOT receive any notifications, even if they have outstanding payments or upcoming expiry.

### Setup

1. Identify or create a test contractor account.
2. Mark account as frozen: `is_frozen = TRUE`.
3. Mark the contractor as having an outstanding payment and/or upcoming expiry.

### Steps

1. **Trigger payment and expiry reminder jobs**:
   - Run: `php artisan send:grace-period-reminders && php artisan send:renewal-reminders`.

2. **Verify NO notifications are sent**:
   - Wait 2–3 minutes.
   - Contractor device should NOT receive any notifications.
   - In-app notification list should NOT show new records (or should not be accessible if account is frozen).

3. **Unfreeze the account**:
   - Set `is_frozen = FALSE`.

4. **Re-run jobs**:
   - Run: `php artisan send:grace-period-reminders && php artisan send:renewal-reminders`.
   - **Expected**: Notifications are now sent (account is unfrozen).

### Acceptance Criteria

- [ ] Frozen contractors receive zero notifications (FR-011, US2/4).
- [ ] Notifications are sent once account is unfrozen.

---

## Scenario 9: No Device Token — In-App Record Still Created

**Goal**: Verify graceful handling of missing FCM device token.

### Setup

1. Identify or create a test contractor account.
2. Remove or clear the FCM token: `fcm_token = NULL`.
3. Mark the contractor as having an outstanding payment.

### Steps

1. **Trigger payment reminder job**:
   - Run: `php artisan send:grace-period-reminders`.

2. **Verify NO push is sent**:
   - Wait 2–3 minutes.
   - Contractor device should NOT receive a push notification (no device token).

3. **Verify in-app record IS created**:
   - Log in as contractor (if account is accessible; if frozen due to no token, skip this).
   - Navigate to: Notifications.
   - **Expected**: In-app notification record exists for the payment reminder.
   - The contractor can still see the reminder in the app, even though push wasn't sent.

4. **Register device token**:
   - Contractor (or test script) registers an FCM token.

5. **Re-run job (next day)**:
   - Wait until next day or manually increment `notification_run.run_date`.
   - Run: `php artisan send:grace-period-reminders`.
   - **Expected**: Now that device token is registered, push is sent.

### Acceptance Criteria

- [ ] Missing device token does not block in-app notification creation (FR-013).
- [ ] In-app notification is persisted and accessible.
- [ ] Push is sent once device token is registered (on next job run).

---

## Scenario 10: Bulk Broadcast Performance

**Goal**: Verify that broadcasting an announcement to 1000+ contractors completes within acceptable time.

### Setup (Staging Environment Only)

1. Ensure test contractor base has at least 100 active (non-frozen) contractors with FCM tokens.

### Steps

1. **Publish an announcement targeting all contractors**:
   - Admin publishes an announcement with audience "All Active Contractors".

2. **Measure broadcast time**:
   - Record the time from "Publish" button click to all notifications created.
   - **Expected**: Broadcast to 1000 contractors completes within 2–5 minutes (depends on FCM API rate limits).
   - Check logs: no errors or timeouts.

3. **Verify all contractors received**:
   - Spot-check 5–10 contractors:
     - In-app notification list shows the announcement.
     - Device received push (if token registered).

### Acceptance Criteria

- [ ] Broadcast to 1000+ contractors completes within acceptable time.
- [ ] All contractors have in-app record.
- [ ] No partial failures or timeout errors.

---

## API Testing (Optional, for backend dev)

If you prefer to test via API directly (e.g., curl, Postman):

### Create a Payment Reminder Notification (Direct API)

```bash
curl -X POST http://localhost:8000/api/v1/admin/notifications/create \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "contractor_id": 1,
    "type": "payment_reminder",
    "title": "تذكير بدفع الاشتراك",
    "body": "لديك اشتراك مستحق. يرجى الدفع الآن.",
    "action_url": "/contractor/payment"
  }'
```

### Fetch Contractor Notifications

```bash
curl http://localhost:8000/api/v1/contractor/notifications \
  -H "Authorization: Bearer $CONTRACTOR_TOKEN"
```

### Mark Notification as Read

```bash
curl -X PATCH http://localhost:8000/api/v1/contractor/notifications/42 \
  -H "Authorization: Bearer $CONTRACTOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{ "read": true }'
```

---

## Troubleshooting

### Notification Not Received on Device

1. **Check device token registration**:
   - Confirm `fcm_token` is not null on contractor record.
   - Verify token is valid (device is running app and connected).

2. **Check job execution**:
   - Verify the reminder job ran (check logs: `storage/logs/laravel.log`).
   - Confirm contractor matched the query (e.g., outstanding payment, within expiry window).

3. **Check Firebase Console**:
   - Log in to Firebase Console (project `pcu-gaza`).
   - Check Message Logs: does the push appear as sent/failed?

4. **Device Logs**:
   - On Android: `adb logcat | grep FCM` (if using emulator).
   - On iOS: Xcode console or device console.

### Duplicate Notifications

1. **Check `notification_runs` table**:
   - Verify only one entry per job per day.
   - If multiple entries exist, a job may have been run multiple times. Clear extra entries or fix scheduling.

2. **Check notification creation time**:
   - Query: `SELECT * FROM notifications WHERE contractor_id = ? AND created_at >= NOW() - INTERVAL 1 HOUR`.
   - Should show only one per type per job run.

### In-App Notification Not Appearing

1. **Check Notification table**:
   - Verify record was inserted: `SELECT * FROM notifications WHERE contractor_id = ? ORDER BY created_at DESC LIMIT 1`.
   - Check `type`, `title`, `body` are correct.

2. **Check frontend notification list**:
   - Verify contractor's app is fetching `/contractor/notifications` endpoint.
   - Check network tab: response includes the notification?

3. **Frontend pagination/sorting**:
   - If notification list is paginated, verify the record is on page 1 (or check sorting).

---

## Checklist Before Launch

- [ ] Scenario 1: Announcement broadcast works (push + in-app).
- [ ] Scenario 2: Draft announcements do not notify.
- [ ] Scenario 3: Editing published announcements does not re-notify.
- [ ] Scenario 4: Payment reminders sent to contractors with outstanding payments.
- [ ] Scenario 5: Payment reminders NOT sent to fully-paid contractors.
- [ ] Scenario 6: Expiry reminders sent to contractors within the warning window.
- [ ] Scenario 7: Expiry reminders NOT sent to contractors outside the window.
- [ ] Scenario 8: Frozen contractors receive NO notifications.
- [ ] Scenario 9: Missing device token does not block in-app record.
- [ ] Scenario 10: Bulk broadcast completes within acceptable time (staging only).
- [ ] All notifications are in Arabic.
- [ ] Tapping notifications opens the correct page/screen in the app.
- [ ] Running jobs multiple times same day does NOT create duplicates (idempotency verified).
- [ ] Admin permissions are enforced (only admins can publish announcements, trigger jobs).
- [ ] Firebase Cloud Messaging is configured and credentials are in place.

---

**Next Step**: After manual validation passes, proceed to Phase 2 implementation via `/speckit-tasks`.
