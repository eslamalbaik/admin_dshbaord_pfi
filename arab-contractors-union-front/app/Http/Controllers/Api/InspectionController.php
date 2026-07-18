<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InspectionController extends Controller
{
    // GET /api/inspections
    public function index(Request $request)
    {
        $query = Inspection::with('contractor', 'inspector');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where('location', 'like', "%{$q}%")
                  ->orWhereHas('contractor', fn($qb) => $qb->where('name', 'like', "%{$q}%"));
        }

        $inspections = $query->latest()->paginate(15)->through(function ($i) {
            return [
                'id'              => $i->id,
                'contractor_name' => $i->contractor?->name,
                'contractor_id'   => $i->contractor_id,
                'location'        => $i->location,
                'lat'             => $i->lat,
                'lng'             => $i->lng,
                'scheduled_at'    => $i->scheduled_at,
                'status'          => $i->status,
                'notes'           => $i->notes,
                'findings'        => $i->findings,
                'inspector'       => $i->inspector?->name,
            ];
        });

        return response()->json($inspections);
    }

    // POST /api/inspections
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'location'      => 'nullable|string|max:255',
            'lat'           => 'nullable|numeric|between:-90,90',
            'lng'           => 'nullable|numeric|between:-180,180',
            'scheduled_at'  => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);

        $validated['inspector_id'] = Auth::id();

        $inspection = Inspection::create($validated);

        return response()->json($inspection->load('contractor'), 201);
    }

    // PATCH /api/inspections/{id}
    public function update(Request $request, Inspection $inspection)
    {
        $validated = $request->validate([
            'status'   => 'nullable|in:scheduled,completed,cancelled',
            'findings' => 'nullable|string',
            'notes'    => 'nullable|string',
        ]);

        $inspection->update($validated);

        return response()->json($inspection);
    }
}
