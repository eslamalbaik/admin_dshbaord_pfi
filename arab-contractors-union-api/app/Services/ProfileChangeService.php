<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ProfileChange;
use App\Models\User;
use App\Notifications\ProfileChangesDecidedNotification;
use App\Notifications\ProfileChangesSubmittedNotification;
use App\Support\FieldHistoryContext;
use App\Support\ProfileReview;
use App\Support\UploadLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * مراجعة تعديلات الملف التعريفي (Maker-Checker) — المقاول بيعدّل، الإدارة بتعتمد.
 *
 * مبادئ تجربة المستخدم:
 *   - طلب مفتوح واحد لكل حقل: تعديل جديد بيستبدل المعلّق بدل ما يرفض المقاول.
 *   - رجوع الحقل لقيمته الرسمية بيسحب تعديله تلقائياً.
 *   - القرار لكل حقل لحاله (اعتماد جزئي)، والرفض دايماً مع سبب.
 *   - بيانات التواصل بتنطبّق فوراً، والإدارة بتأكّدها أو بترجّعها.
 */
class ProfileChangeService
{
    /**
     * يستقبل حفظ الملف التعريفي من التطبيق.
     *
     * @param  array  $validated  الحقول النصية المتحقق منها (بدون الملفات)
     * @return array{applied: string[], pending: string[], withdrawn: string[]}
     */
    public function submit(Contractor $contractor, array $validated, Request $request): array
    {
        $result = ['applied' => [], 'pending' => [], 'withdrawn' => []];
        $instant = [];
        $staged = [];

        // الملفات أولاً خارج الـtransaction (رفع على القرص)
        foreach (array_keys(ContractorFileService::FILE_FIELDS) as $field) {
            if ($request->hasFile($field)) {
                $staged[$field] = UploadLimits::storeDocument(
                    $request->file($field),
                    config('profile_review.staging_dir') . '/' . $contractor->id,
                );
            }
        }

        DB::transaction(function () use ($contractor, $validated, $staged, &$result, &$instant) {
            $open = ProfileChange::where('contractor_id', $contractor->id)
                ->pending()
                ->lockForUpdate()
                ->get()
                ->keyBy('field');

            foreach ($validated as $field => $value) {
                $mode = ProfileReview::modeFor($field);
                if (! $mode || ProfileReview::isFile($field)) {
                    // حقول خارج المراجعة (city النصي المشتق مثلاً) بتمشي مع التطبيق الفوري
                    $instant[$field] = $value;
                    continue;
                }

                $official = ProfileReview::normalize($field, $contractor->getAttribute($field));
                $new      = ProfileReview::normalize($field, $value);
                $existing = $open->get($field);

                if ($mode === ProfileChange::MODE_PRE) {
                    if ($new === $official) {
                        // رجّع القيمة للرسمية → سحب تعديله المعلّق إن وُجد
                        if ($existing) {
                            $existing->update(['status' => ProfileChange::STATUS_WITHDRAWN]);
                            $result['withdrawn'][] = $field;
                        }
                        continue;
                    }

                    if ($existing && $existing->new_value === $new) {
                        continue; // نفس الطلب المعلّق — التطبيق بيبعت كل الحقول بكل حفظ
                    }

                    $existing?->update(['status' => ProfileChange::STATUS_SUPERSEDED]);
                    ProfileChange::create([
                        'contractor_id' => $contractor->id,
                        'field'         => $field,
                        'kind'          => 'text',
                        'mode'          => $mode,
                        'old_value'     => $official,
                        'new_value'     => $new,
                    ]);
                    $result['pending'][] = $field;
                    continue;
                }

                // post_review: بينطبّق فوراً
                if ($new === $official) {
                    continue;
                }

                $instant[$field] = $value;
                // تعديل فوري ثاني قبل التأكيد: القيمة «قبل» تضل الأصلية حتى التراجع يرجّع لها
                $originalOld = $existing?->old_value ?? $official;
                $existing?->update(['status' => ProfileChange::STATUS_SUPERSEDED]);

                if ($new === $originalOld) {
                    continue; // رجع للقيمة اللي كانت مؤكَّدة — ما في شي يتراجع
                }

                ProfileChange::create([
                    'contractor_id' => $contractor->id,
                    'field'         => $field,
                    'kind'          => 'text',
                    'mode'          => $mode,
                    'old_value'     => $originalOld,
                    'new_value'     => $new,
                ]);
                $result['applied'][] = $field;
            }

            foreach ($staged as $field => $path) {
                $existing = $open->get($field);
                if ($existing) {
                    $existing->update(['status' => ProfileChange::STATUS_SUPERSEDED]);
                    $this->deleteStagedFile($existing);
                }

                ProfileChange::create([
                    'contractor_id' => $contractor->id,
                    'field'         => $field,
                    'kind'          => 'file',
                    'mode'          => ProfileChange::MODE_PRE,
                    'old_value'     => $contractor->getAttribute($field),
                    'new_value'     => $path,
                ]);
                $result['pending'][] = $field;
            }

            if ($instant) {
                $contractor->update($instant);
            }
        });

        // حقول التطبيق الفوري اللي ما إلها مراجعة (مثل city النصي) ما بتنعدّ كتعديل
        $result['applied'] = array_values(array_unique($result['applied']));

        if ($result['pending'] || $result['applied']) {
            $this->notifyReviewers($contractor, $result);
        }

        return $result;
    }

    /**
     * قرار الإدارة على تعديلات مقاول واحد: approve/reject لكل سطر بانتظار الاعتماد.
     *
     * @param  array<int, array{id:int, action:string, reason?:string|null}>  $decisions
     */
    public function decide(Contractor $contractor, array $decisions, User $reviewer): array
    {
        $approved = [];
        $rejected = [];

        DB::transaction(function () use ($contractor, $decisions, $reviewer, &$approved, &$rejected) {
            $items = ProfileChange::where('contractor_id', $contractor->id)
                ->whereIn('id', array_column($decisions, 'id'))
                ->where('mode', ProfileChange::MODE_PRE)
                ->pending()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $apply = [];
            $applyIds = [];

            foreach ($decisions as $decision) {
                $item = $items->get($decision['id']);
                if (! $item) {
                    continue;
                }

                if ($decision['action'] === 'approve') {
                    $this->assertStillUnique($contractor, $item);
                    $apply[$item->field] = $item->isFile()
                        ? $this->promoteStagedFile($contractor, $item)
                        : $this->castForModel($item->field, $item->new_value);
                    $applyIds[$item->field] = $item->id;
                    $item->fill(['status' => ProfileChange::STATUS_APPROVED]);
                    $approved[] = $item;
                } else {
                    $item->fill([
                        'status'        => ProfileChange::STATUS_REJECTED,
                        'reject_reason' => trim((string) ($decision['reason'] ?? '')),
                    ]);
                    $rejected[] = $item;
                }

                $item->fill(['reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();
            }

            if ($apply) {
                FieldHistoryContext::run([
                    'actor'              => $reviewer,
                    'source'             => 'app',
                    'note'               => 'اعتماد تعديل المقاول من التطبيق',
                    'profile_change_ids' => $applyIds,
                ], fn () => $contractor->update($apply));
            }
        });

        foreach ($rejected as $item) {
            if ($item->isFile()) {
                $this->deleteStagedFile($item);
            }
        }

        if ($approved || $rejected) {
            $contractor->notify(new ProfileChangesDecidedNotification($approved, $rejected));
        }

        \App\Services\AuditLogService::record($reviewer, 'contractor.profile_changes_decided', $contractor, [
            'approved' => array_map(fn ($i) => $i->field, $approved),
            'rejected' => array_map(fn ($i) => $i->field, $rejected),
        ]);

        return ['approved' => count($approved), 'rejected' => count($rejected)];
    }

    /** تأكيد تعديلات التواصل اللي انطبّقت فوراً (جماعي). */
    public function confirm(array $ids, User $reviewer): int
    {
        return ProfileChange::whereIn('id', $ids)
            ->where('mode', ProfileChange::MODE_POST)
            ->pending()
            ->update([
                'status'      => ProfileChange::STATUS_CONFIRMED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'updated_at'  => now(),
            ]);
    }

    /** التراجع عن تعديل تواصل انطبّق فوراً: القيمة السابقة بترجع والمقاول بيوصله السبب. */
    public function revert(ProfileChange $item, User $reviewer, string $reason): void
    {
        if ($item->mode !== ProfileChange::MODE_POST || $item->status !== ProfileChange::STATUS_PENDING) {
            throw ValidationException::withMessages(['id' => 'هذا التعديل لم يعد بانتظار التأكيد.']);
        }

        $contractor = $item->contractor;

        DB::transaction(function () use ($item, $reviewer, $reason, $contractor) {
            $item->update([
                'status'        => ProfileChange::STATUS_REVERTED,
                'reject_reason' => $reason,
                'reviewed_by'   => $reviewer->id,
                'reviewed_at'   => now(),
            ]);

            $values = [$item->field => $this->castForModel($item->field, $item->old_value)];
            if ($item->field === 'city_id') {
                $values['city'] = $item->old_value ? \App\Models\City::find($item->old_value)?->name : null;
            }

            FieldHistoryContext::run([
                'actor'              => $reviewer,
                'note'               => 'تراجع الإدارة عن تعديل المقاول: ' . $reason,
                'profile_change_ids' => [$item->field => $item->id],
            ], fn () => $contractor->update($values));
        });

        $contractor->notify(new ProfileChangesDecidedNotification([], [$item], reverted: true));

        \App\Services\AuditLogService::record($reviewer, 'contractor.profile_change_reverted', $contractor, [
            'field' => $item->field, 'reason' => $reason,
        ]);
    }

    /** المقاول بيسحب تعديل معلّق (حقل واحد أو الكل). */
    public function withdraw(Contractor $contractor, ?int $id = null): int
    {
        $items = ProfileChange::where('contractor_id', $contractor->id)
            ->where('mode', ProfileChange::MODE_PRE)
            ->pending()
            ->when($id, fn ($q) => $q->whereKey($id))
            ->get();

        foreach ($items as $item) {
            $item->update(['status' => ProfileChange::STATUS_WITHDRAWN]);
            if ($item->isFile()) {
                $this->deleteStagedFile($item);
            }
        }

        return $items->count();
    }

    /** حالة المراجعة كما بيحتاجها التطبيق (GET profile وكل حفظ). */
    public function stateFor(Contractor $contractor): array
    {
        $pending = ProfileChange::where('contractor_id', $contractor->id)
            ->where('mode', ProfileChange::MODE_PRE)
            ->pending()
            ->orderBy('created_at')
            ->get();

        // آخر رفض لكل حقل، ما لم يرسل المقاول تعديل أحدث له أو يخفيه، وخلال 30 يوم
        $rejected = ProfileChange::where('contractor_id', $contractor->id)
            ->whereIn('status', [ProfileChange::STATUS_REJECTED, ProfileChange::STATUS_REVERTED])
            ->whereNull('dismissed_at')
            ->where('reviewed_at', '>=', now()->subDays(30))
            ->orderByDesc('id')
            ->get()
            ->unique('field')
            ->filter(fn (ProfileChange $item) => ! ProfileChange::where('contractor_id', $contractor->id)
                ->where('field', $item->field)
                ->where('id', '>', $item->id)
                ->exists())
            ->values();

        return [
            'enabled'          => ProfileReview::enabled(),
            'has_pending'      => $pending->isNotEmpty(),
            'pending_count'    => $pending->count(),
            'pending_since'    => $pending->first()?->created_at?->toISOString(),
            'pending_changes'  => $pending->mapWithKeys(fn ($i) => [$i->field => $this->contractorView($i)])->all(),
            'rejected_changes' => $rejected->mapWithKeys(fn ($i) => [$i->field => $this->contractorView($i)])->all(),
            'review_fields'    => array_merge(ProfileReview::REVIEW_FIELDS, array_keys(ContractorFileService::FILE_FIELDS)),
            'instant_fields'   => ProfileReview::INSTANT_FIELDS,
            'locked_fields'    => ProfileReview::LOCKED_FIELDS,
            'locked_message'   => 'لتعديل هذه البيانات تواصل مع الاتحاد.',
        ];
    }

    /** رسالة الحفظ للتطبيق — بتقول بوضوح شو انحفظ وشو راح للمراجعة. */
    public function message(array $result): string
    {
        $labels = fn (array $fields) => implode('، ', array_map([ProfileReview::class, 'label'], $fields));

        if ($result['pending'] && $result['applied']) {
            return 'تم حفظ بيانات التواصل. وأُرسلت التعديلات التالية لمراجعة الاتحاد وسنبلغك بالنتيجة: ' . $labels($result['pending']) . '.';
        }

        if ($result['pending']) {
            return 'تم إرسال التعديلات لمراجعة الاتحاد وسنبلغك بالنتيجة: ' . $labels($result['pending']) . '. بياناتك الحالية تبقى سارية لحين الاعتماد.';
        }

        if ($result['withdrawn'] && ! $result['applied']) {
            return 'تم إلغاء التعديلات المعلّقة على: ' . $labels($result['withdrawn']) . '.';
        }

        return 'تم تحديث الملف التعريفي بنجاح.';
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function contractorView(ProfileChange $item): array
    {
        return [
            'id'            => $item->id,
            'label'         => ProfileReview::label($item->field),
            'kind'          => $item->kind,
            'value'         => $item->isFile() ? null : $item->new_value,
            'display'       => ProfileReview::display($item->field, $item->new_value),
            'file_url'      => $item->isFile() ? ProfileReview::fileUrl($item->new_value) : null,
            'current_value' => $item->isFile() ? null : $item->old_value,
            'status'        => $item->status,
            'status_label'  => ProfileChange::STATUS_LABELS[$item->status] ?? $item->status,
            'reject_reason' => $item->reject_reason,
            'submitted_at'  => $item->created_at?->toISOString(),
            'reviewed_at'   => $item->reviewed_at?->toISOString(),
        ];
    }

    private function notifyReviewers(Contractor $contractor, array $result): void
    {
        $recipients = User::dashboardRecipients('contractors.profile_changes');
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ProfileChangesSubmittedNotification($contractor, $result['pending'], $result['applied']));
        }
    }

    /** إيميل أو رقم رخصة صار مستخدم عند مقاول ثاني بين الإرسال والاعتماد. */
    private function assertStillUnique(Contractor $contractor, ProfileChange $item): void
    {
        if (! in_array($item->field, ['license_number', 'email'], true) || ! $item->new_value) {
            return;
        }

        $taken = Contractor::where($item->field, $item->new_value)
            ->whereKeyNot($contractor->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'decisions' => ProfileReview::label($item->field) . ' «' . $item->new_value . '» صار مسجلاً لمقاول آخر — ارفض هذا التعديل مع ذكر السبب.',
            ]);
        }
    }

    /** نقل المستند من المجلد المؤقت لمجلده الرسمي. القديم بيضل مؤرشف (ما بينحذف). */
    private function promoteStagedFile(Contractor $contractor, ProfileChange $item): string
    {
        $disk = Storage::disk('public');
        $target = ContractorFileService::FILE_FIELDS[$item->field] . '/' . basename($item->new_value);

        if ($disk->exists($item->new_value) && ! $disk->exists($target)) {
            $disk->move($item->new_value, $target);
        } elseif (! $disk->exists($target)) {
            throw ValidationException::withMessages([
                'decisions' => 'ملف «' . ProfileReview::label($item->field) . '» غير موجود على الخادم — ارفضه واطلب من المقاول رفعه مرة أخرى.',
            ]);
        }

        $item->new_value = $target;

        return $target;
    }

    private function deleteStagedFile(ProfileChange $item): void
    {
        $dir = config('profile_review.staging_dir') . '/';
        if ($item->isFile() && $item->new_value && str_starts_with($item->new_value, $dir)) {
            Storage::disk('public')->delete($item->new_value);
        }
    }

    private function castForModel(string $field, ?string $value): mixed
    {
        return $value;
    }
}
