<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentController extends Controller
{
    use ApiResponseTrait;

    private const TYPES = ['license', 'id', 'contract', 'certificate', 'tender', 'other'];

    // GET /api/documents
    public function index(Request $request)
    {
        $query = Document::with('contractor');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where('title', 'like', "%{$q}%")
                  ->orWhereHas('contractor', fn($qb) => $qb->where('name', 'like', "%{$q}%"));
        }

        if ($request->filled('contractor_id')) {
            $query->where('contractor_id', $request->contractor_id);
        }

        // فرز/تصفية سريعة برقم العضوية — أداة البحث الأساسية لشاشة "وثائق العطاءات" (REQ-Tender-Docs)
        if ($request->filled('membership_number')) {
            $query->whereHas('contractor', fn($qb) => $qb->where('membership_number', $request->membership_number));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $paginator = $query->latest()->paginate(15)->through(fn($d) => [
            'id'                 => $d->id,
            'title'              => $d->title,
            'type'               => $d->type,
            'contractor_name'    => $d->contractor?->name,
            'contractor_id'      => $d->contractor_id,
            'membership_number'  => $d->contractor?->membership_number,
            'url'                => Storage::disk($d->disk)->url($d->url),
            'size'               => $d->size,
            'mime_type'          => $d->mime_type,
            'created_at'         => $d->created_at,
        ]);

        return $this->paginated($paginator);
    }

    // POST /api/documents — رفع الإدارة لوثيقة أي شركة (بأي نوع، بما فيها وثائق العطاء)
    public function store(Request $request)
    {
        $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'title'         => 'required|string|max:255',
            'type'          => 'nullable|in:' . implode(',', self::TYPES),
            'file'          => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store("documents/{$request->contractor_id}", 'public');

        $document = Document::create([
            'contractor_id' => $request->contractor_id,
            'title'         => $request->title,
            'type'          => $request->get('type', 'other'),
            'url'           => $path,
            'disk'          => 'public',
            'size'          => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'uploaded_by'   => Auth::id(),
        ]);

        return $this->success(
            $document->load('contractor')->toArray(),
            'تم رفع الوثيقة بنجاح.',
            201,
        );
    }

    // DELETE /api/documents/{id}
    public function destroy(Document $document)
    {
        Storage::disk($document->disk)->delete($document->url);
        $document->delete();

        return $this->success(message: 'تم حذف الوثيقة بنجاح.');
    }

    // GET /api/documents/export-zip?contractor_id=X — تصدير كل وثائق الشركة المختارة دفعة واحدة (REQ-Tender-Docs)
    public function exportZip(Request $request)
    {
        $request->validate(['contractor_id' => 'required|exists:contractors,id']);

        $contractor = Contractor::findOrFail($request->contractor_id);
        $documents  = Document::where('contractor_id', $contractor->id)->get();

        if ($documents->isEmpty()) {
            return $this->error('لا توجد وثائق لهذه الشركة لتصديرها.', 404);
        }

        $zipDir = storage_path('app/private/temp');
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zipFileName = 'documents-' . $contractor->membership_number . '-' . now()->format('Ymd-His') . '.zip';
        $zipPath     = $zipDir . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->error('تعذّر إنشاء ملف مضغوط.', 500);
        }

        // أسماء الملفات داخل الأرشيف: <النوع>-<العنوان>.<امتداد> — تُميّز الملفات عند فك الضغط
        // مع تصادم عند تكرار نفس العنوان لنفس النوع.
        $usedNames = [];
        foreach ($documents as $document) {
            if (! Storage::disk($document->disk)->exists($document->url)) {
                continue;
            }

            $extension = pathinfo($document->url, PATHINFO_EXTENSION);
            $baseName  = preg_replace('/[^\p{L}\p{N}_\- ]+/u', '_', $document->title) ?: 'document';
            $entryName = "{$document->type}-{$baseName}." . ($extension ?: 'bin');

            $suffix = 1;
            while (in_array($entryName, $usedNames, true)) {
                $entryName = "{$document->type}-{$baseName}-{$suffix}." . ($extension ?: 'bin');
                $suffix++;
            }
            $usedNames[] = $entryName;

            $zip->addFile(Storage::disk($document->disk)->path($document->url), $entryName);
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  واجهة المقاول — رفع/حذف وثائق العطاء الخاصة به فقط (REQ-Tender-Docs)
    // ─────────────────────────────────────────────────────────────────────────

    // GET /api/v1/contractor/documents — راجع ContractorDashboardController::documents (القراءة موجودة أصلاً)

    // POST /api/v1/contractor/documents — المقاول يرفع وثيقة عطاء لملفه الخاص فقط؛ النوع مقفول على tender
    public function contractorStore(Request $request)
    {
        $contractor = $request->user('contractor');

        $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store("documents/{$contractor->id}", 'public');

        $document = Document::create([
            'contractor_id' => $contractor->id,
            'title'         => $request->title,
            'type'          => 'tender',
            'url'           => $path,
            'disk'          => 'public',
            'size'          => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'uploaded_by'   => null,
        ]);

        return $this->success([
            'id'             => $document->id,
            'title'          => $document->title,
            'type'           => $document->type,
            'url'            => Storage::disk($document->disk)->url($document->url),
            'mime_type'      => $document->mime_type,
            'formatted_size' => $document->formatted_size,
            'created_at'     => $document->created_at->toDateString(),
        ], 'تم رفع وثيقة العطاء بنجاح.', 201);
    }

    // DELETE /api/v1/contractor/documents/{document} — حذف وثيقة يملكها المقاول نفسه فقط
    public function contractorDestroy(Request $request, Document $document)
    {
        $contractor = $request->user('contractor');

        if ($document->contractor_id !== $contractor->id) {
            return $this->error('غير مصرح بحذف هذه الوثيقة.', 403);
        }

        Storage::disk($document->disk)->delete($document->url);
        $document->delete();

        return $this->success(message: 'تم حذف الوثيقة بنجاح.');
    }
}
