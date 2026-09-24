<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\AnnouncementPublished;
use App\Models\Notification;
use App\Models\NotificationRun;
use App\Notifications\AnnouncementNotification;
use App\Support\NotificationHelper;
use Illuminate\Support\Facades\Log;

class SendAnnouncementNotifications
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AnnouncementPublished $event): void
    {
        try {
            Log::info('Starting announcement notification distribution', [
                'announcement_id' => $event->announcement->id,
                'announcement_title' => $event->announcement->title,
            ]);

            $announcement = $event->announcement;

            // حارس دفاعي: المتحكّم لا يطلق الحدث إلا عند النشر فعلاً، لكن الإعلان
            // قد يكون مسودة أو مجدولاً لتاريخ مستقبلي — لا نوزّع إشعارات في الحالتين.
            if ($announcement->effective_status !== 'published') {
                Log::info('Skipping announcement notifications — announcement is not published', [
                    'announcement_id' => $announcement->id,
                    'effective_status' => $announcement->effective_status,
                ]);

                return;
            }

            // Get eligible contractors
            $contractors = NotificationHelper::getEligibleContractorsForAnnouncement($announcement);

            Log::info('Found eligible contractors for announcement', [
                'announcement_id' => $announcement->id,
                'eligible_count' => $contractors->count(),
            ]);

            // Create notification run for tracking
            $run = NotificationRun::create([
                'job_name' => 'announcement',
                'run_date' => today(),
                'status' => 'started',
            ]);

            $sentCount = 0;
            $failureCount = 0;

            // Send notification to each eligible contractor
            foreach ($contractors as $contractor) {
                try {
                    // Dispatch the notification
                    $contractor->notify(new AnnouncementNotification($announcement));

                    // Create database record
                    Notification::create([
                        'contractor_id' => $contractor->id,
                        'type' => NotificationType::ANNOUNCEMENT,
                        'title' => 'إعلان جديد',
                        'body' => $announcement->title,
                        'reference_id' => $announcement->id,
                        'reference_type' => 'announcement',
                        'action_url' => "/contractor/announcements/{$announcement->id}",
                        'run_id' => $run->id,
                    ]);

                    $sentCount++;

                    Log::debug('Announcement notification sent', [
                        'announcement_id' => $announcement->id,
                        'contractor_id' => $contractor->id,
                    ]);
                } catch (\Exception $e) {
                    $failureCount++;
                    Log::error('Failed to send announcement notification', [
                        'announcement_id' => $announcement->id,
                        'contractor_id' => $contractor->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Mark run as completed
            $run->markCompleted($sentCount);

            Log::info('Announcement notification distribution completed', [
                'announcement_id' => $announcement->id,
                'sent_count' => $sentCount,
                'failure_count' => $failureCount,
                'run_id' => $run->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error distributing announcement notifications', [
                'announcement_id' => $event->announcement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
