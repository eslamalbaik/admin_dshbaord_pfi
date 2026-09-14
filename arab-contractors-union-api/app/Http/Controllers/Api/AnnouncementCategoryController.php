<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementCategory;
use Illuminate\Http\Request;

class AnnouncementCategoryController extends Controller
{
    // GET /api/v1/announcement-categories
    public function index(Request $request)
    {
        $query = AnnouncementCategory::orderBy('name');

        // منتقي إنشاء/تعديل التعميم يطلب فقط التصنيفات الظاهرة؛ شاشة إدارة التصنيفات تحتاج الكل
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    // POST /api/v1/announcement-categories
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:150',
            'is_active' => 'boolean',
        ]);

        $category = AnnouncementCategory::create($validated);

        return response()->json($category, 201);
    }

    // PATCH /api/v1/announcement-categories/{announcementCategory}
    public function update(Request $request, AnnouncementCategory $announcementCategory)
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:150',
            'is_active' => 'boolean',
        ]);

        $announcementCategory->update($validated);

        return response()->json($announcementCategory);
    }

    // DELETE /api/v1/announcement-categories/{announcementCategory}
    public function destroy(AnnouncementCategory $announcementCategory)
    {
        if ($announcementCategory->announcements()->count() > 0) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا التصنيف لأنه مرتبط بتعميمات موجودة.',
            ], 422);
        }

        $announcementCategory->delete();

        return response()->json(['message' => 'تم حذف التصنيف بنجاح']);
    }
}
