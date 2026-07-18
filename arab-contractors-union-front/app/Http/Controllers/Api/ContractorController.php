<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ContractorController extends Controller
{
    // GET /api/contractors
    public function index(Request $request)
    {
        $query = Contractor::query();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('license_number', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->get('per_page', 10);
        $result  = $query->latest()->paginate($perPage);

        return response()->json($result);
    }

    // POST /api/contractors
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'license_number'   => 'nullable|string|unique:contractors,license_number',
            'trade'            => 'nullable|string|max:100',
            'classification'   => 'nullable|string|max:10',
            'established_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'owner_name'       => 'nullable|string|max:255',
            'email'            => 'nullable|email|unique:contractors,email',
            'phone'            => 'nullable|string|max:20',
            'city'             => 'nullable|string|max:100',
            'address'          => 'nullable|string',
            'notes'            => 'nullable|string',
            'cr_file'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'id_file'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('cr_file')) {
            $validated['cr_file'] = $request->file('cr_file')->store('contractors/cr', 'public');
        }
        if ($request->hasFile('id_file')) {
            $validated['id_file'] = $request->file('id_file')->store('contractors/id', 'public');
        }

        $contractor = Contractor::create($validated);

        return response()->json($contractor, 201);
    }

    // GET /api/contractors/{id}
    public function show(Contractor $contractor)
    {
        return response()->json($contractor->load('activeMembership'));
    }

    // DELETE /api/contractors/{id}
    public function destroy(Contractor $contractor)
    {
        $contractor->delete();
        return response()->json(['message' => 'تم حذف المقاول بنجاح']);
    }

    // POST /api/contractors/{id}/qr
    public function generateQR(Contractor $contractor)
    {
        $expiresAt = now()->addHours(24);

        $payload = Crypt::encryptString(json_encode([
            'contractor_id' => $contractor->id,
            'name'          => $contractor->name,
            'license'       => $contractor->license_number,
            'expires_at'    => $expiresAt->toISOString(),
        ]));

        // نولِّد QR كـ SVG ونحوِّله لـ base64 data URL
        $qrSvg   = base64_encode(QrCode::format('svg')->size(300)->generate($payload));
        $qrUrl   = 'data:image/svg+xml;base64,' . $qrSvg;

        return response()->json([
            'contractor_id' => $contractor->id,
            'token'         => $payload,
            'qr_url'        => $qrUrl,
            'expires_at'    => $expiresAt->toISOString(),
        ]);
    }
}
