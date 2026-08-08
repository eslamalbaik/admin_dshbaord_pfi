<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/announcements — عام (بدون توكن)، منشور فقط
    public function index(Request $request)
    {
        $paginator = Announcement::published()
            ->latest('published_at')
            ->select(['id', 'title', 'body', 'image', 'published_at'])
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($paginator);
    }

    // GET /api/v1/announcements/{announcement}
    public function show(Announcement $announcement)
    {
        if (! $announcement->is_published || ! $announcement->published_at || $announcement->published_at->isFuture()) {
            return $this->error('الإعلان غير متاح.', 404);
        }

        return $this->success($announcement->only(['id', 'title', 'body', 'image', 'published_at']));
    }

    // GET /api/v1/admin/announcements
    public function adminIndex(Request $request)
    {
        $query = Announcement::with('author:id,name')->latest();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', (bool) $request->is_published);
        }

        return $this->paginated($query->paginate(15));
    }

    // POST /api/v1/admin/announcements
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'image'        => 'nullable',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = Storage::disk('public')->url($request->file('image')->store('announcements', 'public'));
        }

        if (($validated['is_published'] ?? false) && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $validated['created_by'] = $request->user()->id;

        $announcement = Announcement::create($validated);

        return $this->success($announcement->toArray(), 'تم نشر الإعلان بنجاح.', 201);
    }

    // PUT /api/v1/admin/announcements/{announcement}
    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'body'         => 'sometimes|string',
            'image'        => 'nullable',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = Storage::disk('public')->url($request->file('image')->store('announcements', 'public'));
        }

        if (($validated['is_published'] ?? false) && ! $announcement->published_at) {
            $validated['published_at'] = now();
        }

        $announcement->update($validated);

        return $this->success($announcement->fresh()->toArray(), 'تم تحديث الإعلان بنجاح.');
    }

    // DELETE /api/v1/admin/announcements/{announcement}
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return $this->success(message: 'تم حذف الإعلان بنجاح.');
    }
}
