<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TenderController extends Controller
{
    use ApiResponseTrait;

    // GET /api/tenders
    public function index(Request $request)
    {
        $query = $this->applyFilters(Tender::query(), $request);

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    // GET /api/v1/tenders-public — قائمة عامة بدون بيانات إدارية
    public function publicIndex(Request $request)
    {
        $query = $this->applyFilters(Tender::query(), $request);

        $paginator = $query->paginate($this->perPage($request))
            ->through(fn ($t) => $this->formatPublic($t));

        return $this->paginated($paginator);
    }

    // GET /api/v1/tenders-public/{tender}
    public function publicShow(Tender $tender)
    {
        return $this->success($this->formatPublic($tender));
    }

    // POST /api/tenders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'union_notes'        => 'nullable|string',
            'category'           => 'nullable|string|max:100',
            'deadline'           => 'nullable|date',
            'status'             => 'nullable|in:open,closed,cancelled',
            'submission_types'   => 'nullable|array',
            'submission_types.*' => 'in:email,phone,file',
            'submission_email'   => 'nullable|email|max:255',
            'submission_phone'   => 'nullable|string|max:20',
            'submission_file'    => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'external_url'       => 'nullable|url|max:500',
        ]);

        if ($request->hasFile('submission_file')) {
            $validated['submission_file'] = $request->file('submission_file')
                ->store('tenders/files', 'public');
        }

        if (isset($validated['submission_types'])) {
            $validated['submission_types'] = array_values(array_filter($validated['submission_types']));
        }

        $validated['created_by'] = Auth::id();
        $tender = Tender::create($validated);

        return $this->success($tender->toArray(), 'تم إضافة العطاء بنجاح.', 201);
    }

    // GET /api/tenders/{id}
    public function show(Tender $tender)
    {
        return $this->success($tender->toArray());
    }

    // PATCH /api/tenders/{id}
    public function update(Request $request, Tender $tender)
    {
        $validated = $request->validate([
            'title'              => 'sometimes|string|max:255',
            'description'        => 'nullable|string',
            'union_notes'        => 'nullable|string',
            'category'           => 'nullable|string|max:100',
            'deadline'           => 'nullable|date',
            'status'             => 'nullable|in:open,closed,cancelled',
            'submission_types'   => 'nullable|array',
            'submission_types.*' => 'in:email,phone,file',
            'submission_email'   => 'nullable|email|max:255',
            'submission_phone'   => 'nullable|string|max:20',
            'submission_file'    => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'external_url'       => 'nullable|url|max:500',
        ]);

        if ($request->hasFile('submission_file')) {
            $validated['submission_file'] = $request->file('submission_file')
                ->store('tenders/files', 'public');
        }

        if (isset($validated['submission_types'])) {
            $validated['submission_types'] = array_values(array_filter($validated['submission_types']));
        }

        $tender->update($validated);

        return $this->success($tender->fresh()->toArray(), 'تم تحديث العطاء بنجاح.');
    }

    // DELETE /api/tenders/{id}
    public function destroy(Tender $tender)
    {
        $tender->delete();

        return $this->success(message: 'تم حذف العطاء بنجاح.');
    }

    /**
     * فلاتر مشتركة بين القائمة الإدارية والعامة:
     * search, status, category, updated_from, updated_to, sort
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->date('updated_from'));
        }
        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->date('updated_to'));
        }

        return match ($request->input('sort')) {
            'oldest'        => $query->oldest(),
            'deadline_asc'  => $query->orderByRaw('deadline IS NULL, deadline asc'),
            'deadline_desc' => $query->orderByRaw('deadline IS NULL, deadline desc'),
            'updated_asc'   => $query->orderBy('updated_at'),
            'updated_desc'  => $query->orderByDesc('updated_at'),
            'budget_asc'    => $query->orderByRaw('budget IS NULL, budget asc'),
            'budget_desc'   => $query->orderByRaw('budget IS NULL, budget desc'),
            default         => $query->latest(),
        };
    }

    private function perPage(Request $request): int
    {
        return min($request->integer('per_page', 15), 100);
    }

    // شكل العطاء المعروض للعامة — بدون union_notes / created_by
    private function formatPublic(Tender $t): array
    {
        return [
            'id'                  => $t->id,
            'title'               => $t->title,
            'description'         => $t->description,
            'category'            => $t->category,
            'budget'              => $t->budget,
            'deadline'            => $t->deadline?->toDateString(),
            'status'              => $t->status,
            'bids_count'          => $t->bids_count,
            'submission_types'    => $t->submission_types,
            'submission_email'    => $t->submission_email,
            'submission_phone'    => $t->submission_phone,
            'submission_file_url' => $t->submission_file
                ? Storage::disk('public')->url($t->submission_file)
                : null,
            'external_url'        => $t->external_url,
            'created_at'          => $t->created_at,
            'updated_at'          => $t->updated_at,
        ];
    }
}
