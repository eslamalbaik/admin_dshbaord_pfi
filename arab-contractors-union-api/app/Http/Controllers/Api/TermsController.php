<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    private array $rules = [
        'type'      => 'nullable|in:terms,privacy',
        'title'     => 'required|string|max:300',
        'title_en'  => 'nullable|string|max:300',
        'body'      => 'required|string',
        'body_en'   => 'nullable|string',
        'sort'      => 'nullable|integer|min:0',
        'is_active' => 'boolean',
    ];

    /** GET /api/v1/terms — Public: نصوص الشروط والأحكام */
    public function public()
    {
        return response()->json(
            Term::where('type', 'terms')
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'title', 'title_en', 'body', 'body_en', 'sort'])
        );
    }

    /** GET /api/v1/privacy-policy — Public: نصوص سياسة الخصوصية */
    public function publicPrivacy()
    {
        return response()->json(
            Term::where('type', 'privacy')
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'title', 'title_en', 'body', 'body_en', 'sort'])
        );
    }

    /** GET /api/v1/dashboard/terms?type=terms|privacy */
    public function index(Request $request)
    {
        $query = Term::orderBy('sort')->orderBy('id');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->get());
    }

    /** POST /api/v1/dashboard/terms */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['type'] = $data['type'] ?? 'terms';

        return response()->json(Term::create($data), 201);
    }

    /** PUT /api/v1/dashboard/terms/{term} */
    public function update(Request $request, Term $term)
    {
        $term->update($request->validate($this->rules));

        return response()->json($term);
    }

    /** DELETE /api/v1/dashboard/terms/{term} */
    public function destroy(Term $term)
    {
        $term->delete();

        return response()->json(['message' => 'Term deleted']);
    }
}
