<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\ProfileUpdateRequestSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * تحويل تعديل ملف الشركة من كتابة مباشرة إلى طلب مراجعة (TASK-17 US11).
 *
 * المشكلة التي يحلّها: التطبيق يُرسل تعديلاته إلى POST contractor/auth/profile/update،
 * وهو مسار يكتب على جدول contractors فوراً؛ أما طابور الموافقات فيقع خلف مسار آخر لا
 * ينادِه أحد. فكان المقاول يعدّل بياناته ولا يظهر أي طلب باللوحة.
 *
 * يُقسّم هذا الفيلر حقول الطلب إلى ثلاث طبقات (ProfileUpdateRequest::REVIEWED_* و
 * INSTANT_FIELDS): الفورية تُكتب كما هي اليوم، والمُراجَعة تُحفَظ كطلب معلّق بلا أي مساس
 * ببيانات المقاول، والمستندات تُرحَّل إلى مسار مؤقّت فلا يُلمس المستند الحالي قبل الموافقة.
 *
 * كل هذا محكوم بمفتاح `profile.edits_require_approval`: إن كان مطفأً فلا شيء من هذا يعمل
 * والسلوك هو سلوك اليوم حرفياً.
 */
class ProfileUpdateRequestFiler
{
    /** مسار ترحيل المستندات — داخل شجرة يستثنيها rsync بـdeploy-vps.sh، فلا يمحوها نشر. */
    private const STAGING_DIR = 'contractors/profile-update/staged';

    public function isEnabled(): bool
    {
        return (bool) config('profile.edits_require_approval', false);
    }

    /**
     * يفصل الحقول الفورية عن المُراجَعة.
     *
     * حقول المستندات تُستبعَد من **الطرفين**: هي كائنات UploadedFile لا قيماً، ومسارها
     * الوحيد هو stageDocuments(). تركها ضمن instant كان يكتب مسار الملف المؤقّت (/tmp/php…)
     * حرفياً في عمود المستند بقاعدة البيانات، فيفقد المقاول مستنده الحقيقي.
     *
     * @return array{instant: array<string,mixed>, reviewed: array<string,mixed>}
     */
    public function partition(array $validated): array
    {
        $reviewedKeys = array_flip(ProfileUpdateRequest::reviewedFields());
        $documentKeys = ProfileUpdateRequest::documentFields();

        $withoutDocuments = array_diff_key($validated, $documentKeys);

        return [
            'instant'  => array_diff_key($withoutDocuments, $reviewedKeys),
            'reviewed' => array_intersect_key($withoutDocuments, $reviewedKeys),
        ];
    }

    /**
     * ينشئ طلباً معلّقاً بالحقول المُراجَعة والمستندات المرفوعة.
     *
     * @param  array<string,mixed> $reviewedData الحقول النصية التي تمرّ بالمراجعة
     * @return ProfileUpdateRequest|null  null إن لم يكن هناك ما يُراجَع فعلاً
     */
    public function file(Request $request, Contractor $contractor, array $reviewedData): ?ProfileUpdateRequest
    {
        $stagedFiles = $this->stageDocuments($request, $contractor);

        if (empty($reviewedData) && empty($stagedFiles)) {
            return null;
        }

        // طلب جديد يُلغي المعلّق السابق بدل أن يُرفَض: مع الملف الكامل، منع التعديل الثاني
        // يعني أن مقاولاً أخطأ في حقل واحد ينتظر أياماً قبل أن يصحّحه — وشاشته تُظهر له
        // "قيد المراجعة" على بيانات يعرف أنها خطأ (D4).
        $this->supersedePending($contractor);

        $profileRequest = $contractor->profileUpdateRequests()->create([
            'proposed_data'  => $reviewedData,
            'proposed_files' => $stagedFiles ?: null,
            'status'         => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ProfileUpdateRequestSubmittedNotification($profileRequest));
        }

        return $profileRequest;
    }

    /**
     * يرفع المستندات إلى مسار مؤقّت باسم الطلب، ولا يلمس مستند المقاول الحالي.
     *
     * @return array<string,string> اسم الحقل → المسار المرحَّل
     */
    private function stageDocuments(Request $request, Contractor $contractor): array
    {
        $staged = [];

        foreach (array_keys(ProfileUpdateRequest::documentFields()) as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $staged[$field] = $request->file($field)->store(
                self::STAGING_DIR . '/' . $contractor->id,
                'public',
            );
        }

        return $staged;
    }

    /** يُلغي الطلب المعلّق السابق ويحذف ملفاته المرحَّلة — لا قيمة لها بعد أن حلّ محلّها طلب أحدث. */
    private function supersedePending(Contractor $contractor): void
    {
        $contractor->profileUpdateRequests()
            ->where('status', 'pending')
            ->get()
            ->each(function (ProfileUpdateRequest $previous) {
                $previous->deleteStagedFiles();
                $previous->update([
                    'status'         => 'superseded',
                    'proposed_files' => null,
                    'superseded_at'  => now(),
                ]);
            });
    }

    /**
     * ينقل مستندات طلب مُوافَق عليه إلى مواضعها النهائية ويحذف المستند المستبدَل.
     *
     * upload-then-delete كما في ContractorFileService: لا يُحذف القديم إلا بعد نجاح النقل،
     * وأي حقل يفشل نقله يُسجَّل ولا يُفرَّغ عمودُه — فقدان مستند قائم أسوأ من عدم تحديثه.
     *
     * @return array<string,string> اسم الحقل → المسار النهائي، لتطبيقه على المقاول
     */
    public function promoteDocuments(ProfileUpdateRequest $profileRequest, Contractor $contractor): array
    {
        $applied = [];
        $paths   = ProfileUpdateRequest::documentFields();

        foreach ($profileRequest->proposed_files ?? [] as $field => $stagedPath) {
            // حصر صريح: لا يُكتب إلا اسم حقل مستند معروف، ولو تلاعب أحد بمحتوى العمود
            if (! isset($paths[$field]) || ! Storage::disk('public')->exists($stagedPath)) {
                Log::warning('profile_update_request.staged_file_missing', [
                    'request_id' => $profileRequest->id,
                    'field'      => $field,
                    'path'       => $stagedPath,
                ]);
                continue;
            }

            $finalPath = $paths[$field] . '/' . basename($stagedPath);

            if (! Storage::disk('public')->move($stagedPath, $finalPath)) {
                Log::warning('profile_update_request.staged_file_move_failed', [
                    'request_id' => $profileRequest->id,
                    'field'      => $field,
                ]);
                continue;
            }

            if ($contractor->$field) {
                Storage::disk('public')->delete($contractor->$field);
            }

            $applied[$field] = $finalPath;
        }

        return $applied;
    }
}
