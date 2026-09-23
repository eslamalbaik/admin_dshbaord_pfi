<?php

namespace Tests\Feature;

use App\Http\Middleware\DetectDiscardedRequestBody;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TASK-16 #3 — DetectDiscardedRequestBody مُسجَّل على مجموعة api كاملة، فخطأ إيجابي
 * كاذب فيه يُعطّل كل نقاط النهاية دفعة واحدة. هذه الاختبارات تحرس الشرط نفسه.
 *
 * ملاحظة: بيئة الاختبار لا تملأ $_POST/$_FILES (طلبات Symfony لا تمرّ بمحلّل PHP)،
 * لذلك تُستدعى الـ middleware مباشرة مع ضبط الـ superglobals يدوياً — استدعاؤها عبر
 * HTTP داخل الاختبار يُثبت فقط أنها لا تتدخّل، لا أنها تكتشف الحالة فعلاً.
 */
class DiscardedRequestBodyTest extends TestCase
{
    use RefreshDatabase;

    private function runMiddleware(Request $request)
    {
        return (new DetectDiscardedRequestBody)->handle($request, fn () => response('passed-through'));
    }

    private function multipartRequest(int $contentLength): Request
    {
        $request = Request::create('/api/v1/contractors', 'POST');
        $request->headers->set('Content-Type', 'multipart/form-data; boundary=----x');
        $request->server->set('CONTENT_LENGTH', $contentLength);

        return $request;
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_FILES = [];
        parent::tearDown();
    }

    public function test_returns_413_when_php_discarded_a_multipart_body(): void
    {
        $_POST  = [];
        $_FILES = [];

        $response = $this->runMiddleware($this->multipartRequest(50_000_000));

        $this->assertEquals(413, $response->getStatusCode());
        $this->assertStringContainsString('حجم المرفقات', $response->getData()->message);
    }

    public function test_passes_through_when_post_fields_survived(): void
    {
        $_POST  = ['name' => 'شركة'];
        $_FILES = [];

        $response = $this->runMiddleware($this->multipartRequest(50_000_000));

        $this->assertEquals('passed-through', $response->getContent());
    }

    public function test_passes_through_when_only_files_survived(): void
    {
        $_POST  = [];
        $_FILES = ['cr_file' => ['name' => 'a.pdf', 'error' => 0]];

        $response = $this->runMiddleware($this->multipartRequest(50_000_000));

        $this->assertEquals('passed-through', $response->getContent());
    }

    public function test_ignores_requests_without_a_body(): void
    {
        $_POST  = [];
        $_FILES = [];

        $response = $this->runMiddleware($this->multipartRequest(0));

        $this->assertEquals('passed-through', $response->getContent());
    }

    /** JSON الخام يصل عبر php://input ولا يملأ $_POST أصلاً — لا يجوز اعتباره مُسقَطاً. */
    public function test_ignores_non_multipart_requests(): void
    {
        $_POST  = [];
        $_FILES = [];

        $request = Request::create('/api/v1/contractors', 'POST', [], [], [], [], '{"name":"شركة"}');
        $request->headers->set('Content-Type', 'application/json');
        $request->server->set('CONTENT_LENGTH', 500);

        $this->assertEquals('passed-through', $this->runMiddleware($request)->getContent());
    }

    public function test_ignores_get_requests(): void
    {
        $_POST  = [];
        $_FILES = [];

        $request = Request::create('/api/v1/contractors', 'GET');
        $request->headers->set('Content-Type', 'multipart/form-data');
        $request->server->set('CONTENT_LENGTH', 50_000_000);

        $this->assertEquals('passed-through', $this->runMiddleware($request)->getContent());
    }

    /** حراسة تكاملية: المسارات العادية لا تتأثر بوجود الـ middleware في المجموعة. */
    public function test_public_endpoint_still_responds_normally(): void
    {
        $this->getJson('/api/v1/tenders-public')->assertStatus(200);
    }
}
