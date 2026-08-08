<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketContractorRepliedNotification;
use App\Notifications\SupportTicketCreatedNotification;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SupportTicketController extends Controller
{
    use ApiResponseTrait;

    /** تنسيق تذكرة لاستجابة الـ API */
    private function format(SupportTicket $t): array
    {
        return [
            'id'             => $t->id,
            'contractor_id'  => $t->contractor_id,
            'contractor'     => $t->contractor?->name,
            'subject'        => $t->subject,
            'category'       => $t->category,
            'category_label' => $t->category_label,
            'message'        => $t->message,
            'attachment_url' => $t->attachment_url,
            'status'         => $t->status,
            'status_label'   => $t->status_label,
            'reply'          => $t->reply,
            'replied_by'     => $t->repliedBy?->name,
            'replied_at'     => $t->replied_at,
            'created_at'     => $t->created_at,
            'messages'       => $this->formatMessages($t),
        ];
    }

    /** سجل المحادثة الكامل — الرسالة الأصلية أول فقاعة، ثم كل الردود بالترتيب الزمني */
    private function formatMessages(SupportTicket $t): array
    {
        return $t->messages->map(fn ($m) => [
            'id'             => $m->id,
            'sender_type'    => $m->sender_type,
            'sender_name'    => $m->sender_name,
            'message'        => $m->message,
            'attachment_url' => $m->attachment_url,
            'created_at'     => $m->created_at,
        ])->values()->all();
    }

    /**
     * تنسيق تفاصيل تذكرة — شكل أغنى من القائمة:
     * مرفق ككائن، الرد ككتلة متداخلة، وبيانات المقاول.
     */
    private function formatDetails(SupportTicket $t): array
    {
        return [
            'id'             => $t->id,
            'subject'        => $t->subject,
            'category'       => $t->category,
            'category_label' => $t->category_label,
            'status'         => $t->status,
            'status_label'   => $t->status_label,
            'message'        => $t->message,
            'attachment'     => $t->attachment ? [
                'url'       => $t->attachment_url,
                'name'      => basename($t->attachment),
                'extension' => pathinfo($t->attachment, PATHINFO_EXTENSION),
            ] : null,
            'reply'          => $t->reply ? [
                'text'       => $t->reply,
                'replied_by' => $t->repliedBy?->name,
                'replied_at' => $t->replied_at,
            ] : null,
            'messages'       => $this->formatMessages($t),
            'contractor'     => $t->contractor ? [
                'id'                => $t->contractor->id,
                'name'              => $t->contractor->name,
                'membership_number' => $t->contractor->membership_number,
            ] : null,
            'created_at'     => $t->created_at,
            'updated_at'     => $t->updated_at,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — شاشة الدعم الفني والشكاوى
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * POST /api/v1/contractor/support-tickets
     * يرسل المقاول طلب دعم/شكوى للإدارة.
     */
    public function store(Request $request)
    {
        $contractor = $request->user();

        $data = $request->validate([
            'subject'    => 'required|string|max:255',
            'category'   => 'required|in:technical,complaint,inquiry,suggestion,other',
            'message'    => 'required|string|max:5000',
            'attachment' => 'nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('support-attachments', 'public');
        }

        $ticket = SupportTicket::create([
            'contractor_id' => $contractor->id,
            'subject'       => $data['subject'],
            'category'      => $data['category'],
            'message'       => $data['message'],
            'attachment'    => $data['attachment'] ?? null,
            'status'        => 'open',
        ]);

        // إشعار الإدارة بوجود طلب دعم جديد
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SupportTicketCreatedNotification($ticket));
        }

        return $this->success(
            $this->format($ticket),
            'تم إرسال طلبك بنجاح، وسيتم الرد عليك في أقرب وقت.',
            201,
        );
    }

    /**
     * GET /api/v1/contractor/support-tickets
     * قائمة تذاكر المقاول الحالي.
     */
    public function myTickets(Request $request)
    {
        $paginator = SupportTicket::with(['repliedBy:id,name', 'messages'])
            ->where('contractor_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($t) => $this->format($t));

        return $this->paginated($paginator);
    }

    /**
     * GET /api/v1/contractor/support-tickets/{ticket}
     * تفاصيل تذكرة للمقاول (تذاكره فقط).
     */
    public function showMine(Request $request, SupportTicket $ticket)
    {
        if ($ticket->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرّح بالوصول إلى هذا الطلب.', 403);
        }

        return $this->success($this->formatDetails(
            $ticket->load(['repliedBy:id,name', 'contractor:id,name,membership_number', 'messages'])
        ));
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/dashboard/support-tickets */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['contractor:id,name', 'repliedBy:id,name', 'messages']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($qb) =>
                $qb->where('subject', 'like', "%{$q}%")
                   ->orWhereHas('contractor', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            );
        }

        $paginator = $query->latest()->paginate(15)->through(fn ($t) => $this->format($t));

        return $this->paginated($paginator);
    }

    /** GET /api/v1/dashboard/support-tickets/{ticket} */
    public function show(SupportTicket $ticket)
    {
        return $this->success($this->formatDetails(
            $ticket->load(['contractor:id,name,membership_number', 'repliedBy:id,name', 'messages'])
        ));
    }

    /**
     * POST /api/v1/dashboard/support-tickets/{ticket}/reply
     * ترد الإدارة على التذكرة (رسالة جديدة بالمحادثة) → إشعار للمقاول عبر البريد والتطبيق.
     */
    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'reply' => 'required|string|max:5000',
        ]);

        $ticket->messages()->create([
            'sender_type' => 'admin',
            'sender_id'   => Auth::id(),
            'message'     => $data['reply'],
        ]);

        // نحافظ على reply/replied_by/replied_at كـ"آخر رد" لعرضه بقائمة التذاكر بدون تحميل المحادثة كاملة
        $ticket->update([
            'reply'      => $data['reply'],
            'status'     => $ticket->status === 'closed' ? 'closed' : 'answered',
            'replied_by' => Auth::id(),
            'replied_at' => now(),
        ]);

        // إشعار المقاول عبر البريد الإلكتروني وإشعار داخل التطبيق — فشل الإشعار (مثلاً تعليق SMTP)
        // ما لازم يفشّل الرد نفسه، لأن الرد أصلاً نجح بالداتابيز قبل هالسطر.
        if ($ticket->contractor) {
            try {
                $ticket->contractor->notify(new SupportTicketRepliedNotification($ticket));
            }
            catch (\Throwable $e) {
                Log::warning('Failed to send support ticket reply notification', [
                    'ticket_id' => $ticket->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $this->success(
            $this->format($ticket->fresh(['contractor:id,name', 'repliedBy:id,name', 'messages'])),
            'تم إرسال الرد بنجاح.',
        );
    }

    /**
     * POST /api/v1/contractor/support-tickets/{ticket}/reply
     * يضيف المقاول رسالة متابعة على تذكرته — يعيد فتحها تلقائياً إن كانت "تم الرد".
     */
    public function replyMine(Request $request, SupportTicket $ticket)
    {
        $contractor = $request->user();

        if ($ticket->contractor_id !== $contractor->id) {
            return $this->error('غير مصرّح بالوصول إلى هذا الطلب.', 403);
        }

        if ($ticket->status === 'closed') {
            return $this->error('هذا الطلب مغلق ولا يمكن إضافة رسائل جديدة عليه.', 422);
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $ticket->messages()->create([
            'sender_type' => 'contractor',
            'sender_id'   => $contractor->id,
            'message'     => $data['message'],
        ]);

        if ($ticket->status === 'answered') {
            $ticket->update(['status' => 'open']);
        }

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            try {
                Notification::send($admins, new SupportTicketContractorRepliedNotification($ticket));
            }
            catch (\Throwable $e) {
                Log::warning('Failed to send contractor-follow-up notification', [
                    'ticket_id' => $ticket->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $this->success(
            $this->formatDetails($ticket->fresh(['repliedBy:id,name', 'contractor:id,name,membership_number', 'messages'])),
            'تم إرسال رسالتك بنجاح.',
        );
    }

    /** PATCH /api/v1/dashboard/support-tickets/{ticket}/status */
    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,answered,closed',
        ]);

        $ticket->update(['status' => $data['status']]);

        return $this->success($this->format($ticket), 'تم تحديث حالة الطلب.');
    }

    /** DELETE /api/v1/dashboard/support-tickets/{ticket} */
    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();

        return $this->success(message: 'تم حذف الطلب.');
    }
}
