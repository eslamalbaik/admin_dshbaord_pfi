<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    // GET /api/documents
    public function index(Request $request)
    {
        $query = Document::with('contractor');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where('title', 'like', "%{$q}%")
                  ->orWhereHas('contractor', fn($qb) => $qb->where('name', 'like', "%{$q}%"));
        }

        if ($request->filled('contractor_id')) {
            $query->where('contractor_id', $request->contractor_id);
        }

        $documents = $query->latest()->paginate(15)->through(function ($d) {
            return [
                'id'              => $d->id,
                'title'           => $d->title,
                'type'            => $d->type,
                'contractor_name' => $d->contractor?->name,
                'contractor_id'   => $d->contractor_id,
                'url'             => Storage::disk($d->disk)->url($d->url),
                'size'            => $d->size,
                'mime_type'       => $d->mime_type,
                'created_at'      => $d->created_at,
            ];
        });

        return response()->json($documents);
    }

    // POST /api/documents
    public function store(Request $request)
    {
        $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'title'         => 'required|string|max:255',
            'type'          => 'nullable|in:license,id,contract,certificate,other',
            'file'          => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store("documents/{$request->contractor_id}", 'public');

        $document = Document::create([
            'contractor_id' => $request->contractor_id,
            'title'         => $request->title,
            'type'          => $request->get('type', 'other'),
            'url'           => $path,
            'disk'          => 'public',
            'size'          => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'uploaded_by'   => Auth::id(),
        ]);

        return response()->json($document->load('contractor'), 201);
    }

    // DELETE /api/documents/{id}
    public function destroy(Document $document)
    {
        Storage::disk($document->disk)->delete($document->url);
        $document->delete();
        return response()->json(['message' => 'تم حذف الوثيقة']);
    }
}
