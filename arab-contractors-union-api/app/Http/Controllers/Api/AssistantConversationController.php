<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use Illuminate\Http\Request;

/** لوحة التحكم: سجل محادثات المساعد الافتراضي (سامي) مع زوار الموقع */
class AssistantConversationController extends Controller
{
    // GET /api/v1/dashboard/assistant-conversations?search=&source=ai&per_page=20
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $source = $request->query('source');

        $q = AssistantConversation::query()
            ->with('firstQuestion:id,assistant_conversation_id,text')
            ->withCount(['messages as questions_count' => fn ($m) => $m->where('role', 'user')])
            ->withCount(['messages as ai_answers_count' => fn ($m) => $m->where('source', 'ai')])
            ->withCount(['messages as unanswered_count' => fn ($m) => $m->where('source', 'fallback')])
            ->when($search !== '', fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('text', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->when(in_array($source, ['ai', 'faq', 'smalltalk', 'page', 'fallback'], true), fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('source', $source)))
            ->orderByDesc('last_message_at')->orderByDesc('id');

        $page = $q->paginate(min(max((int) $request->query('per_page', 20), 5), 100));

        return response()->json([
            'items' => collect($page->items())->map(fn (AssistantConversation $c) => [
                'id'               => $c->id,
                'first_question'   => $c->firstQuestion?->text,
                'messages_count'   => $c->messages_count,
                'questions_count'  => $c->questions_count,
                'ai_answers_count' => $c->ai_answers_count,
                'unanswered_count' => $c->unanswered_count,
                'page'             => $c->page,
                'device'           => $this->device($c->user_agent),
                'started_at'       => $c->created_at?->toIso8601String(),
                'last_message_at'  => $c->last_message_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'today'        => AssistantConversation::where('created_at', '>=', now()->startOfDay())->count(),
            ],
        ]);
    }

    // GET /api/v1/dashboard/assistant-conversations/{id}
    public function show(AssistantConversation $assistantConversation)
    {
        return response()->json([
            'id'         => $assistantConversation->id,
            'page'       => $assistantConversation->page,
            'device'     => $this->device($assistantConversation->user_agent),
            'started_at' => $assistantConversation->created_at?->toIso8601String(),
            'messages'   => $assistantConversation->messages()->get(['id', 'role', 'text', 'source', 'created_at'])
                ->map(fn ($m) => [
                    'id'         => $m->id,
                    'role'       => $m->role,
                    'text'       => $m->text,
                    'source'     => $m->source,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
        ]);
    }

    // DELETE /api/v1/dashboard/assistant-conversations/{id}
    public function destroy(AssistantConversation $assistantConversation)
    {
        $assistantConversation->delete();

        return response()->json(['message' => 'تم حذف المحادثة']);
    }

    private function device(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }

        return preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? 'موبايل' : 'كمبيوتر';
    }
}
