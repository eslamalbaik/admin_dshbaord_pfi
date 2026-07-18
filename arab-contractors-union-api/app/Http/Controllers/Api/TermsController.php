<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    private array $rules = [
        'title'     => 'required|string|max:300',
        'title_en'  => 'nullable|string|max:300',
        'body'      => 'required|string',
        'body_en'   => 'nullable|string',
        'sort'      => 'nullable|integer|min:0',
        'is_active' => 'boolean',
    ];

    /** GET /api/v1/terms  — Public endpoint for the landing page */
    public function public()
    {
        return response()->json(
            Term::where('is_active', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'title', 'title_en', 'body', 'body_en', 'sort'])
        );
    }

    /** GET /api/v1/dashboard/terms */
    public function index()
    {
        return response()->json(
            Term::orderBy('sort')->orderBy('id')->get()
        );
    }

    /** POST /api/v1/dashboard/terms */
    public function store(Request $request)
    {
        return response()->json(Term::create($request->validate($this->rules)), 201);
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
