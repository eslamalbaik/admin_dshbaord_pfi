<?php

namespace Tests\Feature;

use App\Services\Sms\HotSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** شكل طلب HotSMS — بدون أي إرسال فعلي (Http::fake). */
class HotSmsSenderTest extends TestCase
{
    public function test_v1_sends_get_request_with_api_token_and_mobile_without_plus(): void
    {
        Http::fake(['hotsms.ps/*' => Http::response('1001', 200)]);

        $sent = (new HotSmsSender('test-token', 'PCU', 'v1', '0'))->send('+201001234567', 'رمز التحقق: 123456');

        $this->assertTrue($sent);
        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://hotsms.ps/sendbulksms.php?')
                && $request['api_token'] === 'test-token'
                && $request['sender'] === 'PCU'
                && $request['mobile'] === '201001234567'
                && $request['type'] === '0'
                && $request['text'] === 'رمز التحقق: 123456';
        });
    }

    public function test_v1_converts_local_palestinian_number(): void
    {
        Http::fake(['hotsms.ps/*' => Http::response('1001', 200)]);

        (new HotSmsSender('t', 'PCU'))->send('0599123456', 'x');

        Http::assertSent(fn (Request $request) => $request['mobile'] === '970599123456');
    }

    public function test_v1_http_error_counts_as_failure(): void
    {
        Http::fake(['hotsms.ps/*' => Http::response('error', 500)]);

        $this->assertFalse((new HotSmsSender('t', 'PCU'))->send('+970599123456', 'x'));
    }

    public function test_v2_posts_with_bearer_token(): void
    {
        Http::fake(['hotsms.ps/*' => Http::response(['status' => true], 200)]);

        $this->assertTrue((new HotSmsSender('t', 'PCU', 'v2'))->send('+970599123456', 'x'));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer t')
            && $request['mobile'] === '+970599123456');
    }
}
