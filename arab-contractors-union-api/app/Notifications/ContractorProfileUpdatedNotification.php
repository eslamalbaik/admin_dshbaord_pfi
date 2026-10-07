<?php

namespace App\Notifications;

use App\Models\Contractor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار الإدارة بأن مقاولاً عدّل بيانات ملفه التعريفي مباشرة (بدون مراجعة مسبقة) —
 * بديل طابور طلبات التعديل: التعديل يُطبَّق فوراً، والإدارة تُبلَّغ لاحقاً بما تغيّر.
 * لا يشمل الحقول المقفلة (name, membership_number, commercial_register, owner_name,
 * authorized_person*, partners, specialties, classification) لأنها أصلاً غير قابلة
 * للتعديل من بوابة/تطبيق المقاول.
 */
class ContractorProfileUpdatedNotification extends Notification
{
    use Queueable;

    /** @param array<string,array{old:mixed,new:mixed}> $changes حقل → قيمة قديمة/جديدة */
    public function __construct(protected Contractor $contractor, protected array $changes)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $fields = implode('، ', array_keys($this->changes));

        return [
            'type'              => 'contractor_profile_updated',
            'contractor_id'     => $this->contractor->id,
            'contractor_name'   => $this->contractor->name,
            'membership_number' => $this->contractor->membership_number,
            'changed_fields'    => $this->changes,
            'title'             => 'تعديل بيانات ملف مقاول',
            'message'           => "قام {$this->contractor->name} بتعديل بيانات ملفه التعريفي: {$fields}.",
        ];
    }
}
