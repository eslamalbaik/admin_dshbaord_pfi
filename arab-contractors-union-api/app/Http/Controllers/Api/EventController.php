<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\HandlesMediaUploads;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponseTrait, HandlesMediaUploads;

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — الفعاليات + RSVP (REQ-21)
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/events
    public function contractorEvents(Request $request)
    {
        $query = Event::published()->orderBy('event_date');

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        $paginator = $query
            ->select([
                'id', 'title', 'slug', 'excerpt', 'body', 'image', 'gallery',
                'event_date', 'event_location', 'event_format', 'is_international', 'stream_url', 'speakers',
                'published_at',
            ])
            ->withCount('registrations')
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($e) => $this->formatEvent($e, $request->user()));

        return $this->paginated($paginator);
    }

    // GET /api/v1/contractor/events/{event}
    public function contractorEventShow(Request $request, Event $event)
    {
        $event->loadCount('registrations');

        return $this->success($this->formatEvent($event, $request->user()));
    }

    // POST /api/v1/contractor/events/{event}/join
    public function joinEvent(Request $request, Event $event)
    {
        EventRegistration::firstOrCreate(
            ['event_id' => $event->id, 'contractor_id' => $request->user()->id],
            ['registered_at' => now()],
        );

        return $this->success(message: 'تم تسجيل انضمامك للفعالية بنجاح.');
    }

    // DELETE /api/v1/contractor/events/{event}/join
    public function leaveEvent(Request $request, Event $event)
    {
        EventRegistration::where('event_id', $event->id)
            ->where('contractor_id', $request->user()->id)
            ->delete();

        return $this->success(message: 'تم إلغاء انضمامك للفعالية.');
    }

    private function formatEvent(Event $e, ?Contractor $contractor = null): array
    {
        // رابط البث "يُفعَّل يوم الفعالية" — يُخفى قبلها ولو كان مُعبّأً بالإدارة
        $streamAvailable = $e->event_date && now()->isSameDay($e->event_date);

        return [
            'id'                => $e->id,
            'title'             => $e->title,
            'slug'              => $e->slug,
            'excerpt'           => $e->excerpt,
            'body'              => $e->body,
            'image'             => $e->image,
            'gallery'           => $e->gallery,
            'event_date'        => $e->event_date,
            'event_location'    => $e->event_location,
            // نوع الحضور: onsite (وجاهي) | online (أونلاين) | hybrid (وجاهي + أونلاين)
            'event_format'      => $e->event_format,
            'is_international'  => (bool) $e->is_international,
            'stream_url'        => $streamAvailable ? $e->stream_url : null,
            'stream_available'  => $streamAvailable,
            'speakers'          => $e->speakers ?? [],
            'attendees_count'   => $e->registrations_count ?? 0,
            'is_registered'     => $contractor
                ? EventRegistration::where('event_id', $e->id)->where('contractor_id', $contractor->id)->exists()
                : false,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin — لوحة تتبع حضور الفعالية
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/events/{event}/attendees
    public function attendees(Event $event)
    {
        $registrations = $event->registrations()
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

    // ═════════════════════════════════════════════════════════════════════════
    //  الموقع العام (Public) — بدون توثيق
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/events
    public function index(Request $request)
    {
        $query = Event::published()->orderBy('event_date');

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        $paginator = $query
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'published_at', 'event_date', 'event_location'])
            ->paginate($request->integer('per_page', 9));

        return $this->paginated($paginator);
    }

    // GET /api/v1/events/latest
    public function latest()
    {
        $events = Event::published()
            ->orderBy('event_date')
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'published_at', 'event_date', 'event_location'])
            ->limit(3)
            ->get();

        return $this->success($events->toArray());
    }

    // GET /api/v1/events/{slug}
    public function show(string $slug)
    {
        $event = Event::published()
            ->with('author:id,name')
            ->where('slug', $slug)
            ->firstOrFail();

        // رابط البث يُخفى قبل يوم الفعالية حتى بالصفحة العامة — نفس قاعدة formatEvent
        $streamAvailable = $event->event_date && now()->isSameDay($event->event_date);
        $data = $event->toArray();
        $data['stream_url'] = $streamAvailable ? $event->stream_url : null;
        $data['stream_available'] = $streamAvailable;

        return $this->success($data);
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard (Protected)
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/events
    public function adminIndex(Request $request)
    {
        $query = Event::with('author:id,name')->withCount('registrations')->latest();

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        if ($request->filled('is_published'))
            $query->where('is_published', (bool) $request->is_published);

        return $this->paginated($query->paginate(15));
    }

    // POST /api/v1/admin/events
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
            'is_published'   => 'boolean',
            'published_at'   => 'nullable|date',
            'event_date'     => 'nullable|date',
            'event_location' => 'nullable|string|max:255',
            'event_format'      => 'nullable|in:onsite,online,hybrid',
            'is_international'  => 'boolean',
            'stream_url'        => 'nullable|url|max:500',
            'speakers'          => 'nullable|array',
            'speakers.*.name'   => 'required_with:speakers|string|max:255',
            'speakers.*.title'  => 'nullable|string|max:255',
            'speakers.*.photo'  => 'nullable|url|max:500',
            'speakers.*.is_keynote' => 'boolean',
        ]);

        $this->handleMediaUploads($request, $validated, null, 'events', 'events/gallery');

        $validated['slug']       = Event::generateSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;

        if (($validated['is_published'] ?? false) && empty($validated['published_at']))
            $validated['published_at'] = now();

        $event = Event::create($validated);

        if ($event->is_published) {
            \App\Jobs\SendPushToContractorsJob::dispatch(
                Contractor::whereNotNull('fcm_token')->pluck('id')->all(),
                'فعالية جديدة',
                $event->title,
                ['type' => 'event', 'news_id' => (string) $event->id],
            );
        }

        return $this->success($event->toArray(), 'تم نشر الفعالية بنجاح.', 201);
    }

    // PUT /api/v1/admin/events/{id}
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'excerpt'        => 'nullable|string|max:500',
            'body'           => 'sometimes|string',
            'image'          => 'nullable',
            'video_url'      => 'nullable|url|max:500',
            'external_url'   => 'nullable|url|max:500',
            'gallery'        => 'nullable|array',
            'remove_gallery'   => 'nullable|array',
            'remove_gallery.*' => 'string',
            'is_published'   => 'boolean',
            'published_at'   => 'nullable|date',
            'event_date'     => 'nullable|date',
            'event_location' => 'nullable|string|max:255',
            'event_format'      => 'nullable|in:onsite,online,hybrid',
            'is_international'  => 'boolean',
            'stream_url'        => 'nullable|url|max:500',
            'speakers'          => 'nullable|array',
            'speakers.*.name'   => 'required_with:speakers|string|max:255',
            'speakers.*.title'  => 'nullable|string|max:255',
            'speakers.*.photo'  => 'nullable|url|max:500',
            'speakers.*.is_keynote' => 'boolean',
        ]);

        $this->handleMediaUploads($request, $validated, $event, 'events', 'events/gallery');

        if (isset($validated['title']))
            $validated['slug'] = Event::generateSlug($validated['title']);

        $newlyPublished = ($validated['is_published'] ?? false) && ! $event->published_at;
        if ($newlyPublished)
            $validated['published_at'] = now();

        $event->update($validated);

        if ($newlyPublished) {
            \App\Jobs\SendPushToContractorsJob::dispatch(
                Contractor::whereNotNull('fcm_token')->pluck('id')->all(),
                'فعالية جديدة',
                $event->title,
                ['type' => 'event', 'news_id' => (string) $event->id],
            );
        }

        return $this->success($event->fresh()->toArray(), 'تم تحديث الفعالية بنجاح.');
    }

    // DELETE /api/v1/admin/events/{id}
    public function destroy(Event $event)
    {
        $event->delete();

        return $this->success(message: 'تم حذف الفعالية بنجاح.');
    }
}
