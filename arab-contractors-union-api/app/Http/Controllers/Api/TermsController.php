<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TermsController extends Controller
{
    private array $rules = [
        'type'      => 'nullable|in:terms,privacy',
        'title'     => 'required|string|max:300',
        'title_en'  => 'nullable|string|max:300',
        'body'      => 'required|string',
        'body_en'   => 'nullable|string',
        'sort'      => 'nullable|integer|min:0',
        'is_active' => 'boolean',
    ];

    /** GET /api/v1/terms — Public: نصوص الشروط والأحكام */
    public function public()
    {
        return response()->json(
            Term::where('type', 'terms')
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'title', 'title_en', 'body', 'body_en', 'sort'])
        );
    }

    /** GET /api/v1/privacy-policy — Public: نصوص سياسة الخصوصية */
    public function publicPrivacy()
    {
        return response()->json(
            Term::where('type', 'privacy')
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'title', 'title_en', 'body', 'body_en', 'sort'])
        );
    }

    /** GET /api/v1/dashboard/terms?type=terms|privacy */
    public function index(Request $request)
    {
        $query = Term::orderBy('sort')->orderBy('id');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->get());
    }

    /** POST /api/v1/dashboard/terms */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['type'] = $data['type'] ?? 'terms';

        return DB::transaction(function () use ($data, $request) {
            // لو رقم الترتيب المطلوب محجوز مسبقاً، نزحزح كل من بعده للأسفل ليُفسح مكاناً (بدل تكرار الرقم)
            if (isset($data['sort'])) {
                Term::where('type', $data['type'])
                    ->where('sort', '>=', $data['sort'])
                    ->increment('sort');
            }

            $term = Term::create($data);
            AuditLogService::record($request->user(), 'term.created', $term, ['type' => $term->type, 'title' => $term->title]);

            return response()->json($term, 201);
        });
    }

    /** PUT /api/v1/dashboard/terms/{term} */
    public function update(Request $request, Term $term)
    {
        $data = $request->validate($this->rules);

        $oldSort = $term->sort;
        $newSort = $data['sort'] ?? null;
        $type    = $data['type'] ?? $term->type;

        if ($newSort !== null && $newSort !== $oldSort) {
            DB::transaction(function () use ($term, $data, $oldSort, $newSort, $type) {
                if ($newSort > $oldSort) {
                    // تحرّك للأسفل بالترتيب: كل من كان بين الموضع القديم والجديد يتقدّم رقماً واحداً للأعلى
                    Term::where('type', $type)
                        ->where('id', '!=', $term->id)
                        ->whereBetween('sort', [$oldSort + 1, $newSort])
                        ->decrement('sort');
                } else {
                    // تحرّك للأعلى بالترتيب: كل من كان بين الموضعين يتأخر رقماً واحداً للأسفل
                    Term::where('type', $type)
                        ->where('id', '!=', $term->id)
                        ->whereBetween('sort', [$newSort, $oldSort - 1])
                        ->increment('sort');
                }

                $term->update($data);
            });
        } else {
            $term->update($data);
        }

        AuditLogService::record($request->user(), 'term.updated', $term, ['type' => $term->type, 'title' => $term->title]);

        return response()->json($term->fresh());
    }

    /** DELETE /api/v1/dashboard/terms/{term} */
    public function destroy(Request $request, Term $term)
    {
        AuditLogService::record($request->user(), 'term.deleted', $term, ['type' => $term->type, 'title' => $term->title]);

        $term->delete();

        return response()->json(['message' => 'Term deleted']);
    }
}
