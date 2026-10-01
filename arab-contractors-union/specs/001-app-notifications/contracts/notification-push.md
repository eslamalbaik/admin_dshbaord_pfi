# Contract: FCM Push Notification Payload

**Date**: 2026-09-22 | **Phase**: 1 (Design)

**Scope**: Firebase Cloud Messaging (FCM) payload contract for contractor push notifications (announcements, payment reminders, expiry reminders).

---

## Overview

Contractors receive push notifications via Firebase Cloud Messaging (FCM). Each push is accompanied by a persisted in-app notification record (see [data-model.md](../data-model.md)).

This contract defines:
1. FCM payload structure (data, notification, extras).
2. Handling for Android vs. iOS differences.
3. Deep linking and tap action behavior.
4. Localization (Arabic).

---

## FCM Payload Structure

### Generic Payload (sent to FCM)

```json
{
  "notification": {
    "title": "إعلان جديد",
    "body": "تحديث سياسة الاشتراك متاح الآن"
  },
  "data": {
    "type": "announcement",
    "reference_id": "12",
    "reference_type": "Announcement",
    "action_url": "/contractor/announcements/12",
    "priority": "high"
  },
  "android": {
    "priority": "high",
    "notification": {
      "title": "إعلان جديد",
      "body": "تحديث سياسة الاشتراك متاح الآن",
      "click_action": "ANNOUNCEMENT_TAPPED",
      "tag": "announcement_12"
    }
  },
  "apns": {
    "headers": {
      "apns-priority": "10"
    },
    "payload": {
      "aps": {
        "alert": {
          "title": "إعلان جديد",
          "body": "تحديث سياسة الاشتراك متاح الآن"
        },
        "badge": 1,
        "sound": "default"
      }
    }
  }
}
```

### Payload Fields

#### `notification` (Top-Level)

| Field | Type | Required | Value | Notes |
|-------|------|----------|-------|-------|
| `title` | string | YES | Arabic title (max 255 chars) | Shown as push heading on device |
| `body` | string | YES | Arabic body (max 240 chars) | Shown as push preview on device |

#### `data` (Context)

| Field | Type | Required | Value | Notes |
|-------|------|----------|-------|-------|
| `type` | enum | YES | `announcement`, `payment_reminder`, `expiry_reminder` | Identifies notification type |
| `reference_id` | string | YES (if applicable) | UUID or numeric ID | ID of referenced entity (Announcement, etc.) |
| `reference_type` | string | YES (if applicable) | `Announcement`, `ContractorDue`, `Contractor` | Type of referenced entity |
| `action_url` | string | YES | Relative path (e.g., `/contractor/announcements/12`) | Deep link on tap; no domain |
| `priority` | enum | NO | `high`, `normal` | Delivery priority (default: normal) |

#### `android` (Android-Specific)

| Field | Type | Notes |
|-------|------|-------|
| `priority` | enum | `high` or `normal`; high = immediate delivery |
| `notification.click_action` | string | Receiver broadcast action (e.g., `ANNOUNCEMENT_TAPPED`); mobile app listens and navigates |
| `notification.tag` | string | Group/collapse key to prevent duplicate notification cards (e.g., `announcement_12`) |

#### `apns` (Apple-Specific)

| Field | Type | Notes |
|-------|------|-------|
| `headers.apns-priority` | string | `10` (high) or `5` (low) |
| `payload.aps.alert.title` | string | iOS notification title |
| `payload.aps.alert.body` | string | iOS notification body |
| `payload.aps.badge` | integer | Badge count on app icon (app is responsible for incrementing and clearing) |
| `payload.aps.sound` | string | `default` or no sound |

---

## Notification Types & Payloads

### Type 1: Announcement Notification

**When sent**: Admin publishes a new announcement; eligible contractors notified.

**Payload**:

```json
{
  "notification": {
    "title": "إعلان جديد",
    "body": "[announcement.title]"
  },
  "data": {
    "type": "announcement",
    "reference_id": "[announcement.id]",
    "reference_type": "Announcement",
    "action_url": "/contractor/announcements/[announcement.id]",
    "priority": "high"
  }
}
```

**Android Example**:

```json
{
  "android": {
    "priority": "high",
    "notification": {
      "click_action": "ANNOUNCEMENT_TAPPED",
      "tag": "announcement_[announcement.id]"
    }
  }
}
```

**iOS Example**:

```json
{
  "apns": {
    "headers": {
      "apns-priority": "10"
    },
    "payload": {
      "aps": {
        "badge": 1,
        "sound": "default"
      }
    }
  }
}
```

**In-App Record**:

```sql
INSERT INTO notifications (contractor_id, type, title, body, reference_id, reference_type, action_url, run_id, created_at, updated_at)
VALUES (?, 'announcement', 'إعلان جديد', ?, ?, 'Announcement', '/contractor/announcements/?', ?, NOW(), NOW())
```

---

### Type 2: Payment Reminder Notification

**When sent**: Grace-period job runs; contractor has outstanding/unpaid membership subscription.

**Payload**:

```json
{
  "notification": {
    "title": "تذكير بدفع الاشتراك",
    "body": "لديك اشتراك مستحق. يرجى الدفع الآن."
  },
  "data": {
    "type": "payment_reminder",
    "reference_id": "[contractor.id]",
    "reference_type": "Contractor",
    "action_url": "/contractor/payment",
    "priority": "high"
  }
}
```

**Android Example**:

```json
{
  "android": {
    "priority": "high",
    "notification": {
      "click_action": "PAYMENT_REMINDER_TAPPED",
      "tag": "payment_reminder_[contractor.id]"
    }
  }
}
```

**In-App Record**:

```sql
INSERT INTO notifications (contractor_id, type, title, body, reference_id, reference_type, action_url, run_id, created_at, updated_at)
VALUES (?, 'payment_reminder', 'تذكير بدفع الاشتراك', 'لديك اشتراك مستحق. يرجى الدفع الآن.', ?, 'Contractor', '/contractor/payment', ?, NOW(), NOW())
```

---

### Type 3: Expiry Reminder Notification

**When sent**: Renewal job runs; contractor's membership expires within warning window.

**Payload**:

```json
{
  "notification": {
    "title": "تحذير: انتهاء الاشتراك قريبا",
    "body": "اشتراكك ينتهي في [expiry_date]. يرجى التجديد الآن."
  },
  "data": {
    "type": "expiry_reminder",
    "reference_id": "[contractor.id]",
    "reference_type": "Contractor",
    "action_url": "/contractor/renewal",
    "priority": "normal"
  }
}
```

**Android Example**:

```json
{
  "android": {
    "priority": "normal",
    "notification": {
      "click_action": "EXPIRY_REMINDER_TAPPED",
      "tag": "expiry_reminder_[contractor.id]"
    }
  }
}
```

**In-App Record**:

```sql
INSERT INTO notifications (contractor_id, type, title, body, reference_id, reference_type, action_url, run_id, created_at, updated_at)
VALUES (?, 'expiry_reminder', 'تحذير: انتهاء الاشتراك قريبا', 'اشتراكك ينتهي في .... يرجى التجديد الآن.', ?, 'Contractor', '/contractor/renewal', ?, NOW(), NOW())
```

---

## Platform-Specific Handling

### Android

- **Foreground Delivery**: App receives FCM message in `onMessageReceived()` (Firebase SDK).
  - Display notification card using `NotificationCompat.Builder`.
  - Extract `click_action` and `tag` from `android.notification`.
  - Store `action_url` from `data` for deep-link navigation on tap.

- **Background Delivery** (app not running): FCM SDK auto-displays notification card if `notification` block is present.
  - Tap opens app with intent extras populated from `data` block.
  - App's MainActivity parses intent and navigates to `action_url`.

- **Grouping**: Use `tag` field to collapse duplicate notifications (e.g., multiple announcements don't create N separate cards).

### iOS

- **Foreground Delivery**: App receives APNs payload in `application(_:didReceiveRemoteNotification:)` (UIKit) or via `onReceive` modifier (SwiftUI).
  - Parse `aps` from `apns.payload.aps`.
  - Extract `action_url` from `data`.
  - Increment badge (if needed) and show local notification or in-app alert.

- **Background Delivery** (app suspended or not running): Apple's APNs automatically displays notification using `aps.alert`.
  - Tap opens app; app's launch method receives `userInfo` (remoteNotification).
  - Parse `data` and navigate to `action_url`.

- **Badge**: App is responsible for tracking/clearing badge count; recommend clearing on app launch or after reading notifications.

---

## Error Handling

### Missing Device Token

If a contractor has no `fcm_token` registered:
- **Action**: Skip push delivery; log a debug message.
- **In-app Record**: Still created (durable channel).
- **Outcome**: Contractor sees notification in app when they open it, even if push wasn't sent.

### Push Delivery Failure

If FCM API returns an error (e.g., token expired, rate limit, network error):
- **Action**: Log error with contractor ID and token; don't retry (best-effort delivery).
- **In-app Record**: Already persisted; contractor can review later.
- **Outcome**: Notification is durable; missing push is not a critical failure.

### Malformed Payload

If title/body is missing or malformed:
- **Action**: Validation at send time (unit test); abort and log error.
- **In-app Record**: Not created if push payload is invalid (fail-safe).
- **Outcome**: Invalid payloads don't pollute the system.

---

## Localization

**Language**: Arabic (UTF-8 encoded).

All notification titles and bodies are in Arabic (المملكة العربية السعودية conventions). No multi-language support in v1.

**Examples**:

| Type | Title (Arabic) | Body (Arabic) |
|------|---|---|
| Announcement | إعلان جديد | [announcement subject] |
| Payment Reminder | تذكير بدفع الاشتراك | لديك اشتراك مستحق. يرجى الدفع الآن. |
| Expiry Reminder | تحذير: انتهاء الاشتراك قريبا | اشتراكك ينتهي في [date]. يرجى التجديد الآن. |

---

## Testing & Validation

### Unit Test: Payload Generation

Test that:
1. Each notification type produces the correct FCM payload structure.
2. Arabic text is properly UTF-8 encoded.
3. Deep links are relative paths (no domain).
4. Required fields (title, body, type, action_url) are never null.

### Integration Test: Delivery

Test that:
1. Sending to a valid device token succeeds and notification appears on device.
2. Sending to a missing/expired token is logged as a warning (not a failure).
3. Bulk sending (100+ contractors) completes within expected time.

### Acceptance Test (Manual)

1. Create a test contractor with a registered FCM token on a real Android/iOS device.
2. Publish an announcement targeting that contractor.
3. Verify: notification appears on device with correct title/body; tap opens announcement in app.
4. Verify: corresponding in-app notification appears in the contractor's notification list.

---

## Appendix: Laravel Notification Class Template

```php
// app/Notifications/AnnouncementNotification.php

class AnnouncementNotification extends Notification
{
    public $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function via($notifiable)
    {
        return ['database', 'fcm']; // Persist + push
    }

    public function toFcm($notifiable)
    {
        return [
            'notification' => [
                'title' => 'إعلان جديد',
                'body' => $this->announcement->title,
            ],
            'data' => [
                'type' => 'announcement',
                'reference_id' => (string)$this->announcement->id,
                'reference_type' => 'Announcement',
                'action_url' => "/contractor/announcements/{$this->announcement->id}",
                'priority' => 'high',
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'click_action' => 'ANNOUNCEMENT_TAPPED',
                    'tag' => "announcement_{$this->announcement->id}",
                ],
            ],
            'apns' => [
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => 'إعلان جديد',
                            'body' => $this->announcement->title,
                        ],
                        'badge' => 1,
                        'sound' => 'default',
                    ],
                ],
            ],
        ];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'announcement',
            'title' => 'إعلان جديد',
            'body' => $this->announcement->title,
            'reference_id' => $this->announcement->id,
            'reference_type' => 'Announcement',
            'action_url' => "/contractor/announcements/{$this->announcement->id}",
        ];
    }
}
```

---

## Summary

This contract standardizes FCM payload structure across all three notification types, ensures Arabic content is properly encoded, and defines platform-specific handling for Android and iOS. Deep links are always relative paths, allowing the mobile app to construct full URLs as needed.
