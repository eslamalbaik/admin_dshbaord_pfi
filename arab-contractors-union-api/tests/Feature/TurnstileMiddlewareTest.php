<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TurnstileMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::post('/_turnstile-probe', fn () => response()->json(['ok' => true]))->middleware(VerifyTurnstile::class);
    }

    public function test_disabled_without_secret(): void
    {
        config(['services.turnstile.secret' => null]);
        $this->withHeader('Origin', 'https://staging.pcuorg.cloud')->postJson('/_turnstile-probe')->assertOk();
    }

    public function test_native_app_without_origin_passes_unless_strict(): void
    {
        config(['services.turnstile.secret' => 's', 'services.turnstile.strict' => false]);
        $this->postJson('/_turnstile-probe')->assertOk();

        config(['services.turnstile.strict' => true]);
        $this->postJson('/_turnstile-probe')->assertStatus(422)->assertJsonPath('error', 'turnstile_required');
    }

    public function test_browser_request_needs_a_valid_token(): void
    {
        config(['services.turnstile.secret' => 's']);
        $h = ['Origin' => 'https://staging.pcuorg.cloud'];

        $this->postJson('/_turnstile-probe', [], $h)->assertStatus(422)->assertJsonPath('error', 'turnstile_required');

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
            ->push(['success' => true])]);
        $this->postJson('/_turnstile-probe', ['cf_turnstile_token' => 'bad'], $h)->assertStatus(422)->assertJsonPath('error', 'turnstile_failed');

        $this->postJson('/_turnstile-probe', ['cf_turnstile_token' => 'good'], $h)->assertOk();
    }

    public function test_cloudflare_outage_fails_open(): void
    {
        config(['services.turnstile.secret' => 's']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response('', 500)]);
        $this->postJson('/_turnstile-probe', ['cf_turnstile_token' => 'x'], ['Origin' => 'https://staging.pcuorg.cloud'])->assertOk();
    }
}
