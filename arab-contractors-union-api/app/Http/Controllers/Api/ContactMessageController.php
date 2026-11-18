<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactMessageController extends Controller
{
    use ApiResponseTrait;

    // POST /api/v1/contact-messages — عام (نموذج تواصل معنا بالموقع)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:150',
            'email'   => 'required|email|max:190',
            'phone'   => 'nullable|string|max:50',
            'type'    => 'nullable|string|max:100',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        $msg = ContactMessage::create($data);

        $admins = User::dashboardRecipients('services.support_tickets');
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ContactMessageReceivedNotification($msg));
        }

        return $this->success(['id' => $msg->id], 'تم استلام رسالتك بنجاح', 201);
    }

    // GET /api/v1/dashboard/contact-messages
    public function index(Request $request)
    {
        $query = ContactMessage::query()->latest();
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('subject', 'like', $s)->orWhere('email', 'like', $s));
        }

        return $this->paginated($query->paginate(min($request->integer('per_page', 15), 100)));
    }

    // PATCH /api/v1/dashboard/contact-messages/{message}/read
    public function markRead(ContactMessage $message)
    {
        $message->update(['read_at' => $message->read_at ?? now()]);

        return $this->success($message);
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return $this->success(null, 'تم الحذف');
    }
}
