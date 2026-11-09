<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\SiteVideoView;
use Illuminate\Support\Facades\DB;

/**
 * عدّاد مشاهدات فيديوهات الصفحة العامة.
 * المفاتيح محصورة بقائمة ثابتة حتى ما حدا يعبّي الجدول بمفاتيح عشوائية.
 */
class SiteVideoController extends Controller
{
    use ApiResponseTrait;

    public const VIDEOS = ['login-guide'];

    /** GET /api/v1/site-videos/{key} */
    public function show(string $key)
    {
        abort_unless(in_array($key, self::VIDEOS, true), 404);

        return response()->json([
            'success' => true,
            'data'    => ['key' => $key, 'views' => $this->views($key)],
        ]);
    }

    /** POST /api/v1/site-videos/{key}/view — مشاهدة وحدة لكل فتح (الواجهة بتمنع التكرار بنفس الجلسة، والـthrottle بيحد من الـIP) */
    public function view(string $key)
    {
        abort_unless(in_array($key, self::VIDEOS, true), 404);

        DB::transaction(function () use ($key) {
            $row = SiteVideoView::query()->lockForUpdate()->firstOrCreate(['video_key' => $key], ['views' => 0]);
            $row->increment('views');
        });

        return response()->json([
            'success' => true,
            'data'    => ['key' => $key, 'views' => $this->views($key)],
        ]);
    }

    private function views(string $key): int
    {
        return (int) (SiteVideoView::query()->where('video_key', $key)->value('views') ?? 0);
    }
}
