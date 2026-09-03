<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\HandlesMediaUploads;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    use ApiResponseTrait, HandlesMediaUploads;

    // GET /api/v1/news
    public function index(Request $request)
    {
        $query = News::published()->latest('published_at');

        if ($request->filled('category'))
            $query->where('category', $request->category);

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        $paginator = $query
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'category', 'published_at'])
            ->paginate($request->integer('per_page', 9));

        return $this->paginated($paginator);
    }

    // GET /api/v1/news/latest
    public function latest()
    {
        $news = News::published()
            ->latest('published_at')
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'category', 'published_at'])
            ->limit(3)
            ->get();

        return $this->success($news->toArray());
    }

    // GET /api/v1/news/{slug}
    public function show(string $slug)
    {
        $news = News::published()
            ->with('author:id,name')
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success($news->toArray());
    }

    // GET /api/v1/admin/news
    public function adminIndex(Request $request)
    {
        $query = News::with('author:id,name')->latest();

        if ($request->filled('category'))
            $query->where('category', $request->category);

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        if ($request->filled('is_published'))
            $query->where('is_published', (bool) $request->is_published);

        return $this->paginated($query->paginate(15));
    }

    // POST /api/v1/admin/news
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'excerpt'      => 'nullable|string|max:500',
            'body'         => 'required|string',
            'image'        => 'nullable',
            'video_url'    => 'nullable|url|max:500',
            'external_url' => 'nullable|url|max:500',
            'gallery'      => 'nullable|array',
            'category'     => 'required|in:news,announcement,tender',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $this->handleMediaUploads($request, $validated);

        $validated['slug']       = News::generateSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;

        if (($validated['is_published'] ?? false) && empty($validated['published_at']))
            $validated['published_at'] = now();

        $news = News::create($validated);

        return $this->success($news->toArray(), 'تم نشر الخبر بنجاح.', 201);
    }

    // PUT /api/v1/admin/news/{id}
    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'excerpt'          => 'nullable|string|max:500',
            'body'             => 'sometimes|string',
            'image'            => 'nullable',
            'video_url'        => 'nullable|url|max:500',
            'external_url'     => 'nullable|url|max:500',
            'gallery'          => 'nullable|array',
            'remove_gallery'   => 'nullable|array',
            'remove_gallery.*' => 'string',
            'category'         => 'sometimes|in:news,announcement,tender',
            'is_published'     => 'boolean',
            'published_at'     => 'nullable|date',
        ]);

        $this->handleMediaUploads($request, $validated, $news);

        if (isset($validated['title']))
            $validated['slug'] = News::generateSlug($validated['title']);

        $newlyPublished = ($validated['is_published'] ?? false) && ! $news->published_at;
        if ($newlyPublished)
            $validated['published_at'] = now();

        $news->update($validated);

        return $this->success($news->fresh()->toArray(), 'تم تحديث الخبر بنجاح.');
    }

    // DELETE /api/v1/admin/news/{id}
    public function destroy(News $news)
    {
        $news->delete();

        return $this->success(message: 'تم حذف الخبر بنجاح.');
    }
}
