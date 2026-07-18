<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    use ApiResponseTrait;

    private function format(Page $page): array
    {
        return [
            'id'           => $page->id,
            'slug'         => $page->slug,
            'title'        => $page->title,
            'content'      => $page->content,
            'is_published' => $page->is_published,
            'created_by'   => $page->author?->name,
            'created_at'   => $page->created_at,
            'updated_at'   => $page->updated_at,
        ];
    }

    private function rules(?Page $page = null): array
    {
        return [
            'slug'         => [
                $page ? 'sometimes' : 'required',
                'string', 'max:100', 'alpha_dash:ascii',
                Rule::unique('pages', 'slug')->ignore($page?->id),
                Rule::notIn(Page::RESERVED_SLUGS),
            ],
            'title'        => ($page ? 'sometimes' : 'required') . '|string|max:255',
            'content'      => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ];
    }

    /**
     * GET /api/v1/pages/{slug}
     * صفحة عامة منشورة بحسب الـ slug.
     */
    public function showPublic(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->first();

        if (! $page) {
            return $this->error('الصفحة غير موجودة.', 404);
        }

        return $this->success([
            'slug'       => $page->slug,
            'title'      => $page->title,
            'content'    => $page->content,
            'updated_at' => $page->updated_at,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Admin Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/dashboard/pages */
    public function index(Request $request)
    {
        $query = Page::with('author:id,name')->latest();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($qb) => $qb
                ->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"));
        }

        return $this->paginated(
            $query->paginate(min($request->integer('per_page', 15), 100))
                ->through(fn ($p) => $this->format($p))
        );
    }

    /** POST /api/v1/dashboard/pages */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['created_by'] = Auth::id();

        $page = Page::create($data);

        return $this->success($this->format($page), 'تم إنشاء الصفحة بنجاح.', 201);
    }

    /** PUT /api/v1/dashboard/pages/{page} */
    public function update(Request $request, Page $page)
    {
        $data = $request->validate($this->rules($page));

        $page->update($data);

        return $this->success($this->format($page->fresh('author')), 'تم تحديث الصفحة بنجاح.');
    }

    /** DELETE /api/v1/dashboard/pages/{page} */
    public function destroy(Page $page)
    {
        $page->delete();

        return $this->success(message: 'تم حذف الصفحة.');
    }
}
