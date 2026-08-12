<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\EventRegistration;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    use ApiResponseTrait;

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — الفعاليات + RSVP (REQ-21)
    //  الفعالية = خبر بتصنيف category=event (نفس عمود category الموجود مسبقاً)
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/events
    public function contractorEvents(Request $request)
    {
        $query = News::published()->where('category', 'event')->orderBy('event_date');

        $paginator = $query
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'event_date', 'event_location', 'published_at'])
            ->withCount('registrations')
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($n) => $this->formatEvent($n, $request->user()));

        return $this->paginated($paginator);
    }

    // GET /api/v1/contractor/events/{news}
    public function contractorEventShow(Request $request, News $news)
    {
        $news->loadCount('registrations');

        return $this->success($this->formatEvent($news, $request->user()));
    }

    // POST /api/v1/contractor/events/{news}/join
    public function joinEvent(Request $request, News $news)
    {
        EventRegistration::firstOrCreate(
            ['news_id' => $news->id, 'contractor_id' => $request->user()->id],
            ['registered_at' => now()],
        );

        return $this->success(message: 'تم تسجيل انضمامك للفعالية بنجاح.');
    }

    // DELETE /api/v1/contractor/events/{news}/join
    public function leaveEvent(Request $request, News $news)
    {
        EventRegistration::where('news_id', $news->id)
            ->where('contractor_id', $request->user()->id)
            ->delete();

        return $this->success(message: 'تم إلغاء انضمامك للفعالية.');
    }

    private function formatEvent(News $n, ?Contractor $contractor = null): array
    {
        return [
            'id'              => $n->id,
            'title'           => $n->title,
            'slug'            => $n->slug,
            'excerpt'         => $n->excerpt,
            'image'           => $n->image,
            'event_date'      => $n->event_date,
            'event_location'  => $n->event_location,
            'attendees_count' => $n->registrations_count ?? 0,
            'is_registered'   => $contractor
                ? EventRegistration::where('news_id', $n->id)->where('contractor_id', $contractor->id)->exists()
                : false,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin — لوحة تتبع حضور الفعالية
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/dashboard/admin/news/{news}/attendees
    public function attendees(News $news)
    {
        $registrations = $news->registrations()
            ->with('contractor:id,name,membership_number,phone')
            ->latest('registered_at')
            ->get()
            ->map(fn (EventRegistration $r) => [
                'contractor_id'      => $r->contractor_id,
                'name'                => $r->contractor?->name,
                'membership_number'  => $r->contractor?->membership_number,
                'phone'               => $r->contractor?->phone,
                'registered_at'       => $r->registered_at,
            ]);

        return $this->success(['attendees_count' => $registrations->count(), 'attendees' => $registrations]);
    }

    // GET /api/v1/news
    public function index(Request $request)
    {
        $query = News::published()->latest('published_at');

        if ($request->filled('category'))
            $query->where('category', $request->category);

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        $paginator = $query
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'category', 'published_at', 'event_date', 'event_location'])
            ->paginate($request->integer('per_page', 9));

        return $this->paginated($paginator);
    }

    // GET /api/v1/news/latest
    public function latest()
    {
        $news = News::published()
            ->latest('published_at')
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'category', 'published_at', 'event_date', 'event_location'])
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
            'title'          => 'required|string|max:255',
            'excerpt'        => 'nullable|string|max:500',
            'body'           => 'required|string',
            'image'          => 'nullable',
            'video_url'      => 'nullable|url|max:500',
            'external_url'   => 'nullable|url|max:500',
            'gallery'        => 'nullable|array',
            'category'       => 'required|in:news,announcement,event,tender',
            'is_published'   => 'boolean',
            'published_at'   => 'nullable|date',
            'event_date'     => 'nullable|date',
            'event_location' => 'nullable|string|max:255',
        ]);

        $this->handleMediaUploads($request, $validated);

        $validated['slug']       = News::generateSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;

        if (($validated['is_published'] ?? false) && empty($validated['published_at']))
            $validated['published_at'] = now();

        $news = News::create($validated);

        if ($news->category === 'event' && $news->is_published) {
            \App\Jobs\SendPushToContractorsJob::dispatch(
                Contractor::whereNotNull('fcm_token')->pluck('id')->all(),
                'فعالية جديدة',
                $news->title,
                ['type' => 'event', 'news_id' => (string) $news->id],
            );
        }

        return $this->success($news->toArray(), 'تم نشر الخبر بنجاح.', 201);
    }

    // PUT /api/v1/admin/news/{id}
    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'excerpt'        => 'nullable|string|max:500',
            'body'           => 'sometimes|string',
            'image'          => 'nullable',
            'video_url'      => 'nullable|url|max:500',
            'external_url'   => 'nullable|url|max:500',
            'gallery'        => 'nullable|array',
            'category'       => 'sometimes|in:news,announcement,event,tender',
            'is_published'   => 'boolean',
            'published_at'   => 'nullable|date',
            'event_date'     => 'nullable|date',
            'event_location' => 'nullable|string|max:255',
        ]);

        $this->handleMediaUploads($request, $validated);

        if (isset($validated['title']))
            $validated['slug'] = News::generateSlug($validated['title']);

        $newlyPublished = ($validated['is_published'] ?? false) && ! $news->published_at;
        if ($newlyPublished)
            $validated['published_at'] = now();

        $news->update($validated);

        if ($newlyPublished && $news->category === 'event') {
            \App\Jobs\SendPushToContractorsJob::dispatch(
                Contractor::whereNotNull('fcm_token')->pluck('id')->all(),
                'فعالية جديدة',
                $news->title,
                ['type' => 'event', 'news_id' => (string) $news->id],
            );
        }

        return $this->success($news->fresh()->toArray(), 'تم تحديث الخبر بنجاح.');
    }

    // يحوّل ملفات الصورة/المعرض المرفوعة (multipart) إلى روابط عامة داخل مصفوفة $validated
    private function handleMediaUploads(Request $request, array &$validated): void
    {
        if ($request->hasFile('image'))
            $validated['image'] = Storage::disk('public')->url($request->file('image')->store('news', 'public'));

        if ($request->hasFile('gallery')) {
            $validated['gallery'] = array_map(
                fn ($file) => Storage::disk('public')->url($file->store('news/gallery', 'public')),
                $request->file('gallery'),
            );
        }
    }

    // DELETE /api/v1/admin/news/{id}
    public function destroy(News $news)
    {
        $news->delete();

        return $this->success(message: 'تم حذف الخبر بنجاح.');
    }
}
