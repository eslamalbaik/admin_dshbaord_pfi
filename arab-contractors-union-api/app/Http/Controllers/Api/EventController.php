<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\HandlesMediaUploads;
use App\Jobs\SendEventPublishedPushJob;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventJoinedNotification;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use ApiResponseTrait, HandlesMediaUploads;

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — الفعاليات + RSVP (REQ-21)
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/events
    // scope=active|archived — تبويبا "المناسبات الفعالة"/"مؤرشفة" بالتطبيق؛ بدون scope تُرجع الكل
    public function contractorEvents(Request $request)
    {
        $query = Event::published()->orderBy('event_date');

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        if ($request->input('scope') === 'active') {
            $query->active();
        } elseif ($request->input('scope') === 'archived') {
            $query->archived();
        }

        $paginator = $query
            ->select([
                'id', 'title', 'slug', 'excerpt', 'body', 'image', 'gallery',
                'event_date', 'event_location', 'event_format', 'is_international', 'stream_url', 'speakers',
                'published_at', 'archived_at',
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
        $registration = EventRegistration::firstOrCreate(
            ['event_id' => $event->id, 'contractor_id' => $request->user()->id],
            ['registered_at' => now()],
        );

        if ($registration->wasRecentlyCreated) {
            $admins = User::where('role', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new EventJoinedNotification($event, $request->user()));
            }
        }

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
            'is_archived'       => $e->is_archived,
            // فعالية أونلاين ما إلها مكان — حتى لو ضل بالعمود قيمة قديمة من قبل تغيير نوع الحضور
            'event_location'    => $e->event_format === 'online' ? null : $e->event_location,
            // نوع الحضور: onsite (وجاهي) | online (أونلاين) | hybrid (وجاهي + أونلاين)
            'event_format'      => $e->event_format,
            'is_international'  => (bool) $e->is_international,
            'stream_url'        => $streamAvailable ? $e->stream_url : null,
            'stream_available'  => $streamAvailable,
            'speakers'          => $this->normalizeSpeakers($e->speakers ?? []),
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

    // فعالية أونلاين ما إلها مكان بالقوائم العامة كمان — نفس قاعدة formatEvent/show، لأن صفوف قديمة
    // انحفظت قبل clearFieldsHiddenByFormat وضل فيها المكان (مثلاً "gaza" لفعالية event-event على staging)
    private const PUBLIC_LOCATION_COLUMN = "CASE WHEN event_format = 'online' THEN NULL ELSE event_location END AS event_location";

    // GET /api/v1/events
    public function index(Request $request)
    {
        $query = Event::published()->orderBy('event_date');

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        $paginator = $query
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'published_at', 'event_date', DB::raw(self::PUBLIC_LOCATION_COLUMN)])
            ->paginate($request->integer('per_page', 9));

        return $this->paginated($paginator);
    }

    // GET /api/v1/events/latest
    public function latest()
    {
        $events = Event::published()
            ->orderBy('event_date')
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'published_at', 'event_date', DB::raw(self::PUBLIC_LOCATION_COLUMN)])
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
        if ($event->event_format === 'online')
            $data['event_location'] = null;
        $data['stream_available'] = $streamAvailable;
        $data['speakers'] = $this->normalizeSpeakers($data['speakers'] ?? []);

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

        if ($request->input('scope') === 'active') {
            $query->active();
        } elseif ($request->input('scope') === 'archived') {
            $query->archived();
        }

        return $this->paginated($query->paginate(15));
    }

    // الحقل اللي بينخفى بالفورم حسب نوع الحضور بيضل محتفظ بقيمته القديمة (مثلاً مكان "gaza" بعد
    // تحويل الفعالية لأونلاين) — نصفّره هون بدل ما نعتمد على الفرونت يبعته فاضي
    private function clearFieldsHiddenByFormat(array &$validated, ?Event $event = null): void
    {
        $format = array_key_exists('event_format', $validated) ? $validated['event_format'] : $event?->event_format;

        if ($format === 'online')
            $validated['event_location'] = null;
        elseif ($format === 'onsite')
            $validated['stream_url'] = null;
    }

    // القواعد المشتركة بين store/update لحقول الفعالية (باستثناء title/body اللي تختلف required/sometimes)
    // $isCreate: تاريخ النشر لازم يكون اليوم أو بعده بس عند الإنشاء (REQ-11 #2) — التعديل يبقى
    // بلا قيد حتى لا يُمنع تصحيح حقول أخرى بفعالية قديمة تاريخ نشرها بالماضي فعلياً (نفس نمط Tenders/Announcement).
    private function eventRules(bool $isCreate = false): array
    {
        return [
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'video_url'      => 'nullable|url|max:500',
            'external_url'   => 'nullable|url|max:500',
            'is_published'   => 'boolean',
            'published_at'   => $isCreate ? 'nullable|date|after_or_equal:today' : 'nullable|date',
            // موعد الفعالية من بكرا وطالع عند الإنشاء — التعديل بلا قيد (نفس منطق published_at)
            'event_date'     => $isCreate ? 'nullable|date|after_or_equal:tomorrow' : 'nullable|date',
            // مكان الفعالية إلزامي لو نوع الحضور "وجاهي" أو "وجاهي + أونلاين" (بند 9ج) — hybrid
            // كان ناقصاً هون فيقدر الأدمن يحفظ فعالية hybrid بلا مكان رغم إنها تحتاجه فعلياً
            'event_location' => 'required_if:event_format,onsite,hybrid|nullable|string|max:255',
            'event_format'      => 'nullable|in:onsite,online,hybrid',
            'event_type'        => ['nullable', Rule::in(Event::EVENT_TYPES)],
            'stream_url'        => 'nullable|url|max:500',
            'speakers'                  => 'nullable|array',
            'speakers.*.name'           => 'required_with:speakers|string|max:255',
            'speakers.*.title'          => 'nullable|string|max:255',
            // رابط الصورة الحالي (يبقى كما هو لو ما رُفعت صورة جديدة له عبر speaker_photos[])
            'speakers.*.photo'          => 'nullable|string|max:500',
            'speakers.*.is_keynote'     => 'boolean',
            'speaker_photos.*'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ];
    }

    // روابط مكتوبة بدون بروتوكول (www.youtube.com/... أو youtu.be/...) كانت تنرفض بقاعدة url —
    // نكمّلها بـ https:// قبل التحقق بدل ما نرفض رابط صحيح عملياً
    private function normalizeUrls(Request $request): void
    {
        foreach (['video_url', 'external_url', 'stream_url'] as $field) {
            $value = trim((string) $request->input($field, ''));
            if ($value !== '' && ! preg_match('~^[a-z][a-z0-9+.-]*://~i', $value))
                $request->merge([$field => 'https://' . ltrim($value, '/')]);
        }
    }

    // رسائل واضحة لأسباب رفض الصور/التواريخ — تُعرض للأدمن كما هي بالفرونت
    private function eventMessages(): array
    {
        return [
            'image.image'                     => 'الصورة الرئيسية لازم تكون صورة (JPG أو PNG أو WEBP).',
            'image.mimes'                     => 'صيغة الصورة الرئيسية غير مدعومة — المسموح: JPG أو PNG أو WEBP.',
            'image.max'                       => 'حجم الصورة الرئيسية يتجاوز الحد الأقصى 10 ميجابايت.',
            'image.uploaded'                  => 'تعذّر رفع الصورة الرئيسية — تأكد إن حجمها أقل من 10 ميجابايت.',
            'speaker_photos.*.image'          => 'صورة المتحدث لازم تكون صورة (JPG أو PNG أو WEBP).',
            'speaker_photos.*.mimes'          => 'صيغة صورة المتحدث غير مدعومة — المسموح: JPG أو PNG أو WEBP.',
            'speaker_photos.*.max'            => 'حجم صورة المتحدث يتجاوز الحد الأقصى 3 ميجابايت.',
            'speaker_photos.*.uploaded'       => 'تعذّر رفع صورة المتحدث — تأكد إن حجمها أقل من 3 ميجابايت.',
            'event_date.after_or_equal'       => 'موعد الفعالية لازم يكون من بكرا وطالع.',
            'published_at.after_or_equal'     => 'يجب أن يكون تاريخ النشر اليوم أو بعده.',
            'video_url.url'                   => 'رابط فيديو يوتيوب غير صالح — انسخ الرابط كامل من المتصفح.',
            'external_url.url'                => 'الرابط الخارجي غير صالح — انسخ الرابط كامل من المتصفح.',
            'stream_url.url'                  => 'رابط البث المباشر غير صالح — انسخ الرابط كامل من المتصفح.',
        ];
    }

    // يرفع صور المتحدثين المُرسَلة عبر speaker_photos[index] ويدمجها بمصفوفة speakers بنفس الفهرس،
    // ثم يضمن وجود متحدث رئيسي واحد كحد أقصى (بند 9و) — آخر true بالترتيب يفوز لو أُرسل أكثر من واحد
    private function mergeSpeakerPhotosAndEnforceSingleKeynote(Request $request, array &$validated): void
    {
        if ($request->hasFile('speaker_photos')) {
            foreach ($request->file('speaker_photos') as $index => $file) {
                if (! $file || ! isset($validated['speakers'][$index]))
                    continue;

                $path = $file->store('events/speakers', 'public');
                $validated['speakers'][$index]['photo'] = Storage::disk('public')->url($path);
            }
        }

        if (! empty($validated['speakers'])) {
            $keynoteIndex = null;
            foreach ($validated['speakers'] as $i => $sp) {
                if (! empty($sp['is_keynote']))
                    $keynoteIndex = $i;
            }
            foreach ($validated['speakers'] as $i => &$sp) {
                $sp['is_keynote'] = $keynoteIndex !== null && $i === $keynoteIndex;
            }
            unset($sp);
        }
    }

    // POST /api/v1/admin/events
    public function store(Request $request)
    {
        $this->normalizeUrls($request);

        $validated = $request->validate(array_merge([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'is_international' => 'boolean',
        ], $this->eventRules(isCreate: true)), $this->eventMessages());

        $this->handleMediaUploads($request, $validated, null, 'events', 'events/gallery');
        // فعاليات: صورة رئيسية واحدة فقط، لا معرض صور (بند 9د) — handleMediaUploads يقرأ الملفات من
        // الـrequest مباشرة بغض النظر عن قواعد validate، فلازم نُسقط أي gallery بعد المعالجة صراحةً
        unset($validated['gallery']);

        $this->mergeSpeakerPhotosAndEnforceSingleKeynote($request, $validated);
        $this->clearFieldsHiddenByFormat($validated);

        $validated['slug']       = Event::generateSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;

        if (($validated['is_published'] ?? false) && empty($validated['published_at']))
            $validated['published_at'] = now();

        $event = Event::create($validated);

        AuditLogService::record($request->user(), 'event.created', $event, ['title' => $event->title]);

        // الإشعار يوصل وقت ما تبين الفعالية: فوراً للنشر المباشر، وبتاريخ النشر للمجدولة
        SendEventPublishedPushJob::dispatchFor($event);

        $message = ! $event->is_published
            ? 'تم حفظ الفعالية كمسودة.'
            : ($event->published_at?->isFuture() ? 'تمت جدولة الفعالية بنجاح.' : 'تم نشر الفعالية بنجاح.');

        return $this->success($event->toArray(), $message, 201);
    }

    // PUT /api/v1/admin/events/{id}
    public function update(Request $request, Event $event)
    {
        $this->normalizeUrls($request);

        $validated = $request->validate(array_merge([
            'title' => 'sometimes|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'sometimes|string',
            'is_international' => 'boolean',
            'publish_now' => 'boolean',
        ], $this->eventRules()), $this->eventMessages());

        // "نشر مباشرة" لفعالية كانت مسودة أو مجدولة لتاريخ لاحق: تاريخ النشر يصير الآن (نفس نمط الأخبار)
        $publishNow = $request->boolean('publish_now');
        unset($validated['publish_now']);

        $this->handleMediaUploads($request, $validated, $event, 'events', 'events/gallery');
        unset($validated['gallery']);

        $this->mergeSpeakerPhotosAndEnforceSingleKeynote($request, $validated);
        $this->clearFieldsHiddenByFormat($validated, $event);

        if (isset($validated['title']))
            $validated['slug'] = Event::generateSlug($validated['title'], $event->id);

        $newlyPublished = ($validated['is_published'] ?? false) && ! $event->published_at;
        if (($publishNow || $newlyPublished) && empty($validated['published_at']))
            $validated['published_at'] = now();

        // فعالية ما وصل إشعارها بعد = مسودة، أو مجدولة لتاريخ لسا ما إجا
        $notYetAnnounced = ! $event->is_published || ! $event->published_at || $event->published_at->isFuture();

        $event->update($validated);

        AuditLogService::record($request->user(), 'event.updated', $event, ['title' => $event->title]);

        // نشر أول مرة، أو تغيير تاريخ فعالية مجدولة (لقدّام أو "نشر مباشرة") — الـ job القديم
        // بتاريخه القديم بيتجاهل حاله لحاله. تصحيح تاريخ فعالية منشورة أصلاً ما بيعيد الإشعار.
        if ($notYetAnnounced && ($newlyPublished || $event->wasChanged(['published_at', 'is_published'])))
            SendEventPublishedPushJob::dispatchFor($event);

        return $this->success($event->fresh()->toArray(), 'تم تحديث الفعالية بنجاح.');
    }

    // DELETE /api/v1/admin/events/{id}
    public function destroy(Request $request, Event $event)
    {
        AuditLogService::record($request->user(), 'event.deleted', $event, ['title' => $event->title]);

        $event->delete();

        return $this->success(message: 'تم حذف الفعالية بنجاح.');
    }

    private function normalizeSpeakers(array $speakers): array
    {
        return collect($speakers)->map(fn ($sp) => [
            'name' => $sp['name'] ?? null,
            'title' => $sp['title'] ?? null,
            'photo' => $sp['photo'] ?? null,
            'is_keynote' => (bool) ($sp['is_keynote'] ?? false),
        ])->values()->all();
    }
}
