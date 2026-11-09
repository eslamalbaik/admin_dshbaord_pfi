<?php

namespace App\Notifications;

use App\Models\Contractor;
use App\Support\ProfileReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمراجعين (لوحة التحكم): مقاول عدّل ملفه التعريفي من التطبيق.
 * pending = بانتظار الاعتماد، applied = بيانات تواصل انطبّقت فوراً وبانتظار التأكيد.
 */
class ProfileChangesSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $pendingLabels;
    private array $appliedLabels;
    private int $contractorId;
    private string $contractorName;
    private ?string $membershipNumber;

    public function __construct(Contractor $contractor, array $pending, array $applied)
    {
        $this->contractorId     = $contractor->id;
        $this->contractorName   = (string) $contractor->name;
        $this->membershipNumber = $contractor->membership_number;
        $this->pendingLabels    = array_map([ProfileReview::class, 'label'], $pending);
        $this->appliedLabels    = array_map([ProfileReview::class, 'label'], $applied);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $parts = [];
        if ($this->pendingLabels) {
            $parts[] = 'بانتظار الاعتماد: ' . implode('، ', $this->pendingLabels);
        }
        if ($this->appliedLabels) {
            $parts[] = 'انطبّق فوراً: ' . implode('، ', $this->appliedLabels);
        }

        return [
            'type'              => 'contractor_profile_changes',
            'contractor_id'     => $this->contractorId,
            'contractor_name'   => $this->contractorName,
            'membership_number' => $this->membershipNumber,
            'needs_approval'    => (bool) $this->pendingLabels,
            'title'             => $this->pendingLabels ? 'طلب تعديل ملف مقاول' : 'تعديل بيانات تواصل مقاول',
            'message'           => "{$this->contractorName} عدّل ملفه التعريفي — " . implode(' | ', $parts),
        ];
    }
}
