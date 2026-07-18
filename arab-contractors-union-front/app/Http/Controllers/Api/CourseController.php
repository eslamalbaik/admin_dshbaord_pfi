<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $courses = \App\Models\Course::with('instructor')->orderBy('created_at', 'desc')->get();
        return response()->json($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:courses',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'difficulty' => 'nullable|string',
            'language' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'keywords' => 'nullable|string',
            'is_free' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'include_in_subscription' => 'boolean',
            'status' => 'nullable|string'
        ]);

        $validated['instructor_id'] = $request->user()->id;

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('thumbnails', 'public');
            $validated['thumbnail'] = $path;
        }

        $course = \App\Models\Course::create($validated);

        return response()->json(['message' => 'Course created successfully', 'course' => $course], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $course = \App\Models\Course::findOrFail($id);
        
        // Ensure only instructor (or admin) can delete
        if (request()->user()->id !== $course->instructor_id && request()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }
}
