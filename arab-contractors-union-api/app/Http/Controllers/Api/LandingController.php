<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\News;
use App\Models\Setting;
use App\Models\Tender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class LandingController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/landing/home
     * كل بيانات الصفحة الرئيسية في استجابة واحدة (كاش 10 دقائق).
     */
    public function home()
    {
        $items = Cache::remember('landing_home', 600, function () {
            return [
                'maintenance' => [
                    'enabled' => (bool) Setting::get('maintenance_mode', '0'),
                    'message' => Setting::get('maintenance_message', 'الموقع قيد الإنشاء حالياً — نعود إليكم قريباً.'),
                ],
                'union' => [
                    'name'     => Setting::get('union_name', 'اتحاد المقاولين الفلسطينيين — غزة'),
                    'name_en'  => Setting::get('union_name_en', ''),
                    'about'    => Setting::get('union_about', ''),
                    'address'  => Setting::get('union_address', ''),
                    'phone'    => Setting::get('union_phone', ''),
                    'phone2'   => Setting::get('union_phone2', ''),
                    'email'    => Setting::get('union_email', ''),
                    'logo_url' => ($logo = Setting::get('union_logo', ''))
                        ? Storage::disk('public')->url($logo)
                        : null,
                    'social'   => (object) array_filter([
                        'facebook'  => Setting::get('social_facebook', ''),
                        'instagram' => Setting::get('social_instagram', ''),
                        'twitter'   => Setting::get('social_twitter', ''),
                        'linkedin'  => Setting::get('social_linkedin', ''),
                        'youtube'   => Setting::get('social_youtube', ''),
                        'website'   => Setting::get('social_website', ''),
                    ]),
                ],
                'contact' => [
                    'support_whatsapp' => Setting::get('support_whatsapp', ''),
                    'support_email'    => Setting::get('support_email', ''),
                    'support_phone'    => Setting::get('support_phone', ''),
                ],
                'stats' => [
                    // عدّادات حقيقية من قاعدة البيانات
                    'members_count' => Contractor::where('status', 'active')->count(),
                    'tenders_open'  => Tender::where('status', 'open')->count(),
                    'news_count'    => News::published()->count(),
                    // أرقام تسويقية يحدّثها الأدمن من الإعدادات
                    'years'         => (int) Setting::get('stat_years', '30'),
                    'projects'      => (int) Setting::get('stat_projects', '5000'),
                    'branches'      => (int) Setting::get('stat_branches', '11'),
                ],
                'latest_news' => News::published()
                    ->latest('published_at')
                    ->take(6)
                    ->get()
                    ->map(fn ($n) => [
                        'id'           => $n->id,
                        'title'        => $n->title,
                        'slug'         => $n->slug,
                        'excerpt'      => $n->excerpt,
                        'category'     => $n->category,
                        'image_url'    => $n->image ? Storage::disk('public')->url($n->image) : null,
                        'published_at' => $n->published_at,
                    ])
                    ->values(),
            ];
        });

        return $this->success($items);
    }
}
