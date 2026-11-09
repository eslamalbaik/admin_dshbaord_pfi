<?php

namespace App\Observers;

use App\Models\Contractor;
use App\Models\ContractorFieldHistory;
use App\Models\User;
use App\Support\FieldHistoryContext;
use App\Support\ProfileReview;
use Illuminate\Support\Facades\Log;

/**
 * يسجّل كل تغيير فعلي على حقول الملف التعريفي (من التطبيق، لوحة التحكم، أو أوامر النظام)
 * بجدول contractor_field_history: القيمة قبل وبعد ومين عمله. بمستوى الموديل عن قصد،
 * فما في مسار تعديل بيفلت من السجل مهما كان مصدره.
 */
class ContractorFieldHistoryObserver
{
    public function updated(Contractor $contractor): void
    {
        $tracked = array_flip(ProfileReview::trackedFields());
        $changes = array_intersect_key($contractor->getChanges(), $tracked);

        if (! $changes) {
            return;
        }

        $context = FieldHistoryContext::get() ?? [];
        [$source, $actorType, $actorId, $actorName] = $this->actor($context);
        $rows = [];

        foreach (array_keys($changes) as $field) {
            $old = ProfileReview::normalize($field, $contractor->getOriginal($field));
            $new = ProfileReview::normalize($field, $contractor->getAttribute($field));

            if ($old === $new) {
                continue;
            }

            $rows[] = [
                'contractor_id'     => $contractor->id,
                'field'             => $field,
                'old_value'         => $old,
                'new_value'         => $new,
                'source'            => $context['source'] ?? $source,
                'actor_type'        => $actorType,
                'actor_id'          => $actorId,
                'actor_name'        => $actorName,
                'profile_change_id' => $context['profile_change_ids'][$field] ?? null,
                'note'              => $context['note'] ?? null,
                'created_at'        => now(),
            ];
        }

        if (! $rows) {
            return;
        }

        try {
            ContractorFieldHistory::insert($rows);
        } catch (\Throwable $e) {
            // السجل ما لازم يكسر حفظ بيانات المقاول نفسه
            Log::error('contractor_field_history.write_failed', ['contractor_id' => $contractor->id, 'error' => $e->getMessage()]);
        }
    }

    private function actor(array $context): array
    {
        $user = $context['actor'] ?? request()?->user();

        if ($user instanceof Contractor) {
            return ['app', 'contractor', $user->id, $user->name];
        }

        if ($user instanceof User) {
            return ['dashboard', 'user', $user->id, $user->name];
        }

        return ['system', null, null, null];
    }
}
