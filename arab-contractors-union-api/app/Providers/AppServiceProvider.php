<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // مرسل SMS قابل للتوصيل — يبدأ بوضع log ويُستبدل بمزود فعلي عبر SMS_DRIVER
        $this->app->bind(
            \App\Services\Sms\SmsSenderInterface::class,
            fn () => match (config('services.sms.driver', 'log')) {
                'hotsms' => new \App\Services\Sms\HotSmsSender(
                    config('services.sms.hotsms.api_token'),
                    config('services.sms.hotsms.sender'),
                ),
                default => new \App\Services\Sms\LogSmsSender(),
            },
        );

        // مرسل Push (FCM) — log إلى أن يُعرَّف ملف اعتماد Firebase عبر FIREBASE_CREDENTIALS
        $this->app->bind(
            \App\Services\Push\PushSenderInterface::class,
            function () {
                $credentials = config('services.firebase.credentials');

                if (! $credentials || ! file_exists($credentials)) {
                    return new \App\Services\Push\LogPushSender();
                }

                $messaging = (new \Kreait\Firebase\Factory())
                    ->withServiceAccount($credentials)
                    ->createMessaging();

                return new \App\Services\Push\FirebasePushSender($messaging);
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('analytics', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        \App\Models\User::observe(\App\Observers\UserObserver::class);
    }
}
