<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenderController extends Controller
{
    // GET /api/tenders
    public function index(Request $request)
    {
        $query = Tender::query();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(15));
    }

    // POST /api/tenders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'budget'      => 'nullable|numeric|min:0',
            'deadline'    => 'nullable|date',
            'status'      => 'nullable|in:open,closed,cancelled',
        ]);

        $validated['created_by'] = Auth::id();

        $tender = Tender::create($validated);

        return response()->json($tender, 201);
    }

    // GET /api/tenders/{id}
    public function show(Tender $tender)
    {
        return response()->json($tender);
    }

    // PATCH /api/tenders/{id}
    public function update(Request $request, Tender $tender)
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'budget'      => 'nullable|numeric|min:0',
            'deadline'    => 'nullable|date',
            'status'      => 'nullable|in:open,closed,cancelled',
        ]);

        $tender->update($validated);

        return response()->json($tender);
    }

    // DELETE /api/tenders/{id}
    public function destroy(Tender $tender)
    {
        $tender->delete();
        return response()->json(['message' => 'تم حذف المناقصة']);
    }
}
