<?php

/**
 * Notification Configuration
 *
 * This configuration file defines settings for the app notification system,
 * including reminder windows, grace periods, and FCM integration settings.
 */

return [
    /**
     * Renewal reminder window (days before expiry to send reminder)
     */
    'renewal_warning_window_days' => env('RENEWAL_WARNING_WINDOW_DAYS', 7),

    /**
     * Grace period (days after expiry during which reminders are sent)
     */
    'grace_period_days' => env('GRACE_PERIOD_DAYS', 30),

    /**
     * FCM settings
     */
    'fcm' => [
        'enabled' => env('FCM_ENABLED', true),
        'credentials_path' => env('FIREBASE_CREDENTIALS'),
        'max_retry_attempts' => env('FCM_MAX_RETRY_ATTEMPTS', 3),
    ],

    /**
     * Notification scheduling
     */
    'scheduling' => [
        'grace_period_reminder_hour' => env('GRACE_PERIOD_REMINDER_HOUR', 8),
        'renewal_reminder_hour' => env('RENEWAL_REMINDER_HOUR', 9),
    ],

    /**
     * Announcement audience targeting
     */
    'announcement_audience' => [
        'active_only' => true,
        'exclude_frozen' => true,
        'require_device_token' => false, // Set to false to ensure in-app notification is always created
    ],
];
