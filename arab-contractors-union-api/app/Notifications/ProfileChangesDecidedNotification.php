<?php

namespace App\Notifications;

use App\Models\ProfileChange;
use App\Notifications\Channels\FcmChannel;
use App\Support\ProfileReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول بنتيجة مراجعة تعديلات ملفه: المعتمد، والمرفوض مع السبب لكل حقل.
 * reverted = الإدارة تراجعت عن تعديل تواصل كان انطبّق فوراً.
 */
class ProfileChangesDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var string[] */
    private array $approved;

    /** @var array<int, array{field:string, label:string, reason:?string}> */
    private array $rejected;

    /**
     * @param  ProfileChange[]  $approved
     * @param  ProfileChange[]  $rejected
     */
    public function __construct(array $approved, array $rejected, private bool $reverted = false)
    {
        $this->approved = array_map(fn (ProfileChange $i) => ProfileReview::label($i->field), $approved);
        $this->rejected = array_map(fn (ProfileChange $i) => [
            'field'  => $i->field,
            'label'  => ProfileReview::label($i->field),
            'reason' => $i->reject_reason,
        ], $rejected);
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $rejectedText = implode('؛ ', array_map(
            fn ($r) => $r['label'] . ($r['reason'] ? " (السبب: {$r['reason']})" : ''),
            $this->rejected,
        ));

        [$title, $message] = match (true) {
            $this->reverted => [
                'تم التراجع عن تعديل في ملفك',
                'أعاد الاتحاد القيمة السابقة لـ ' . $rejectedText . '. يمكنك تعديلها مجدداً من ملفك التعريفي.',
            ],
            $this->approved && ! $this->rejected => [
                'تم اعتماد تعديلات ملفك التعريفي',
                'اعتمد الاتحاد تعديلاتك: ' . implode('، ', $this->approved) . '.',
            ],
            ! $this->approved => [
                'لم يتم اعتماد تعديلات ملفك التعريفي',
                'لم يعتمد الاتحاد: ' . $rejectedText . '. يمكنك التصحيح وإعادة الإرسال من ملفك التعريفي.',
            ],
            default => [
                'نتيجة مراجعة تعديلات ملفك التعريفي',
                'تم اعتماد: ' . implode('، ', $this->approved) . '. ولم يُعتمد: ' . $rejectedText . '.',
            ],
        };

        return [
            'type'     => $this->reverted ? 'profile_change_reverted' : 'profile_changes_decided',
            'approved' => $this->approved,
            'rejected' => $this->rejected,
            'title'    => $title,
            'message'  => $message,
            // التطبيق بيفتح شاشة الملف التعريفي عند الضغط
            'screen'   => 'profile',
        ];
    }
}
