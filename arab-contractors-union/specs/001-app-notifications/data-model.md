# Data Model: App Notifications

**Date**: 2026-09-22 | **Phase**: 1 (Design)

## Overview

This document defines the database entities required to support contractor notifications (announcements, payment reminders, expiry reminders).

---

## Entity: Notification

**Table**: `notifications`

**Purpose**: Persisted, per-contractor record of a delivered notification. Appears in contractor's in-app notification list; supports push delivery.

### Schema

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | BIGINT UNSIGNED | NO | `auto_increment` | Primary key |
| `contractor_id` | BIGINT UNSIGNED | NO | — | Foreign key to `contractors(id)` |
| `type` | ENUM('announcement', 'payment_reminder', 'expiry_reminder') | NO | — | Notification type |
| `title` | VARCHAR(255) | NO | — | Notification title (Arabic) |
| `body` | TEXT | NO | — | Notification body/message (Arabic) |
| `reference_id` | BIGINT UNSIGNED | YES | NULL | ID of referenced entity (e.g., announcement ID for announcement notifications) |
| `reference_type` | VARCHAR(50) | YES | NULL | Type of referenced entity (e.g., 'Announcement', 'ContractorDue', 'Contractor') — optional, for polymorphic queries |
| `action_url` | VARCHAR(255) | YES | NULL | Deep link or path to open when tapped (e.g., `/contractor/announcements/123`, `/contractor/payment-reminder`) |
| `read_at` | TIMESTAMP | YES | NULL | When the contractor marked the notification as read; NULL if unread |
| `run_id` | BIGINT UNSIGNED | YES | NULL | Foreign key to `notification_runs(id)` — for idempotency tracking |
| `created_at` | TIMESTAMP | NO | `CURRENT_TIMESTAMP` | Created at |
| `updated_at` | TIMESTAMP | NO | `CURRENT_TIMESTAMP` on update | Updated at |

### Indexes

```sql
INDEX idx_contractor_id (contractor_id)
INDEX idx_type (type)
INDEX idx_created_at (created_at)
INDEX idx_contractor_created (contractor_id, created_at DESC)
INDEX idx_reference (reference_id, reference_type)
INDEX idx_run_id (run_id)
```

### Notes

- `read_at` is nullable; contractor-facing UI marks it as read when tapped or via explicit API call.
- `reference_id` + `reference_type` allow querying notifications for a specific item (e.g., all notifications for Announcement #5).
- `action_url` is a relative path (no domain) suitable for deep linking in mobile app; frontend constructs full URL.
- `run_id` links to `notification_runs` for auditability; allows querying all notifications from a specific job run.

---

## Entity: NotificationRun

**Table**: `notification_runs`

**Purpose**: Track when each reminder job (grace-period, renewal) last executed, to prevent same-day duplicate notifications.

### Schema

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | BIGINT UNSIGNED | NO | `auto_increment` | Primary key |
| `job_name` | ENUM('grace-period', 'renewal', 'announcement') | NO | — | Name of the job that produced these notifications |
| `run_date` | DATE | NO | — | Date the job ran (typically today, UTC) |
| `status` | ENUM('started', 'completed', 'failed') | NO | 'started' | Job status (for monitoring/debugging) |
| `contractor_count` | INT UNSIGNED | NO | 0 | Number of contractors notified in this run |
| `error_message` | TEXT | YES | NULL | Error details if status='failed' |
| `created_at` | TIMESTAMP | NO | `CURRENT_TIMESTAMP` | Created at |
| `updated_at` | TIMESTAMP | NO | `CURRENT_TIMESTAMP` on update | Updated at |

### Indexes

```sql
UNIQUE INDEX idx_job_date (job_name, run_date)
INDEX idx_run_date (run_date)
```

### Notes

- **Unique constraint**: `(job_name, run_date)` ensures only one run per job per day.
- **Status tracking**: `started` on insert, `completed` or `failed` on finish, allowing monitoring/retry logic.
- **contractor_count**: Updated at the end of the run for auditing (how many contractors were processed).

---

## Entity: Contractor (existing, extended fields)

**Table**: `contractors`

**Purpose**: Existing contractor model; may need new fields to track device tokens and notification preferences.

### New/Modified Columns (if not already present)

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `fcm_token` | VARCHAR(500) | YES | NULL | Firebase Cloud Messaging token for push delivery (stored here or in separate table, TBD by code review) |
| `notifications_enabled` | BOOLEAN | NO | TRUE | Contractor opt-in/opt-out for notifications (future preference field) |

### Notes

- If `fcm_token` is already stored in a separate `contractor_device_tokens` pivot table, no change needed here.
- `notifications_enabled` is a placeholder for future preference management; v1 may assume all active contractors want notifications.

---

## Entity: Announcement (existing, no changes expected)

**Table**: `announcements`

**Purpose**: Existing model; already has title, body, publish state, and optional audience field.

### Existing Columns (for reference)

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED | — |
| `title` | VARCHAR(255) | Announcement title |
| `body` | TEXT | Announcement body |
| `status` | ENUM('draft', 'published') | Publish state |
| `audience` | VARCHAR(255) | Optional audience segment (e.g., 'all_contractors', 'field_1', 'grade_a'); if absent, broadcast to all active contractors |
| `created_at` | TIMESTAMP | — |
| `updated_at` | TIMESTAMP | — |

### No Changes Required

- The existing Announcement model is sufficient; the notification feature only reads from it.
- The `AnnouncementPublished` event (new or existing) is the trigger.

---

## Related Queries (for reference)

### Find Eligible Contractors for Announcement Broadcast

```sql
SELECT c.* 
FROM contractors c
WHERE c.is_frozen = FALSE 
  AND c.status = 'active'
  AND c.is_verified = TRUE
  AND (
    -- If announcement has an audience filter, apply it; otherwise all active
    -- e.g., IF(:announcement_audience IS NOT NULL, c.field LIKE :audience, TRUE)
  )
```

### Find Contractors with Outstanding Payment (Grace Period)

```sql
SELECT c.* 
FROM contractors c
JOIN contractor_dues cd ON c.id = cd.contractor_id
WHERE c.is_frozen = FALSE
  AND cd.amount > cd.paid_amount
  -- AND c.membership_expires_at < NOW() (in grace period)
```

### Find Contractors Approaching Renewal (Expiry Reminder)

```sql
SELECT c.* 
FROM contractors c
WHERE c.is_frozen = FALSE
  AND c.membership_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 14 DAY)
  -- Assumes 14-day warning window; adjust as needed
```

### Check if Notification Already Sent Today (Idempotency)

```sql
SELECT COUNT(*) FROM notifications n
WHERE n.contractor_id = :contractor_id
  AND n.type = :type
  AND DATE(n.created_at) = CURDATE()
```

**Or** (if using `notification_runs`):

```sql
SELECT COUNT(*) FROM notifications n
JOIN notification_runs nr ON n.run_id = nr.id
WHERE n.contractor_id = :contractor_id
  AND nr.job_name = :job_name
  AND nr.run_date = CURDATE()
```

---

## Migration Strategy

### Migration 1: Create `notification_runs` Table

```php
Schema::create('notification_runs', function (Blueprint $table) {
    $table->id();
    $table->enum('job_name', ['grace-period', 'renewal', 'announcement']);
    $table->date('run_date');
    $table->enum('status', ['started', 'completed', 'failed'])->default('started');
    $table->unsignedInteger('contractor_count')->default(0);
    $table->text('error_message')->nullable();
    $table->timestamps();
    
    $table->unique(['job_name', 'run_date']);
    $table->index('run_date');
});
```

### Migration 2: Create or Extend `notifications` Table

If the table does not exist:

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('contractor_id');
    $table->enum('type', ['announcement', 'payment_reminder', 'expiry_reminder']);
    $table->string('title', 255);
    $table->text('body');
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->string('reference_type', 50)->nullable();
    $table->string('action_url', 255)->nullable();
    $table->timestamp('read_at')->nullable();
    $table->unsignedBigInteger('run_id')->nullable();
    $table->timestamps();
    
    $table->foreign('contractor_id')->references('id')->on('contractors')->onDelete('cascade');
    $table->foreign('run_id')->references('id')->on('notification_runs')->onDelete('set null');
    
    $table->index('contractor_id');
    $table->index('type');
    $table->index('created_at');
    $table->index(['contractor_id', 'created_at']);
    $table->index(['reference_id', 'reference_type']);
    $table->index('run_id');
});
```

If the table exists, extend it with missing columns (e.g., `run_id`, `action_url`, `reference_type`).

### Migration 3: Add Device Token Fields (if needed)

If `fcm_token` is not already present on `contractors`:

```php
Schema::table('contractors', function (Blueprint $table) {
    $table->string('fcm_token', 500)->nullable()->after('id');
    $table->boolean('notifications_enabled')->default(true)->after('fcm_token');
});
```

Or if a separate `contractor_device_tokens` table is used, no change needed.

---

## Enum Types

### NotificationType

```php
// app/Enums/NotificationType.php
enum NotificationType: string
{
    case ANNOUNCEMENT = 'announcement';
    case PAYMENT_REMINDER = 'payment_reminder';
    case EXPIRY_REMINDER = 'expiry_reminder';
}
```

### JobName

```php
// app/Enums/JobName.php
enum JobName: string
{
    case GRACE_PERIOD = 'grace-period';
    case RENEWAL = 'renewal';
    case ANNOUNCEMENT = 'announcement';
}
```

---

## API Response Example

### GET /contractor/notifications (paginated list)

```json
{
  "data": [
    {
      "id": 42,
      "type": "announcement",
      "title": "إعلان جديد: تحديث سياسة الاشتراك",
      "body": "يرجى مراجعة السياسات الجديدة...",
      "action_url": "/contractor/announcements/12",
      "read_at": null,
      "created_at": "2026-09-22T10:30:00Z"
    },
    {
      "id": 41,
      "type": "payment_reminder",
      "title": "تذكير بدفع الاشتراك",
      "body": "لديك اشتراك مستحق. يرجى الدفع الآن.",
      "action_url": "/contractor/payment",
      "read_at": "2026-09-20T14:15:00Z",
      "created_at": "2026-09-20T10:00:00Z"
    }
  ],
  "meta": {
    "total": 50,
    "per_page": 20,
    "current_page": 1
  }
}
```

### Mark Notification as Read

**Request**: `PATCH /contractor/notifications/42`

```json
{
  "read": true
}
```

**Response**:

```json
{
  "id": 42,
  "read_at": "2026-09-22T11:00:00Z"
}
```

---

## Summary

| Entity | Purpose | Status |
|--------|---------|--------|
| **Notification** | In-app notification record | New table or extend existing |
| **NotificationRun** | Idempotency tracking for reminder jobs | New table |
| **Contractor** | May need device token fields | Extend if missing |
| **Announcement** | Trigger for broadcast | No changes (existing) |

All entities follow existing Laravel/Eloquent patterns and MariaDB conventions. No breaking changes to existing models.
