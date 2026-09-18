<?php

namespace Tests\Feature\Print;

use App\Services\Print\PdfRenderer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * V.27 — the server renders the printed document, so web and app print the
 * same bytes. These cases sit on the two ways that silently breaks: the
 * Chromium flags that decide whether the page looks like the browser's print,
 * and images, which vanish because gotenberg cannot reach this app.
 */
class PdfRendererTest extends TestCase
{
    private function fakeGotenberg(): void
    {
        Http::fake([
            '*/forms/chromium/convert/html' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    public function test_it_posts_the_html_to_gotenberg_and_returns_the_bytes(): void
    {
        $this->fakeGotenberg();

        $pdf = app(PdfRenderer::class)->fromHtml('<html><body>Invoice</body></html>');

        $this->assertStringStartsWith('%PDF', $pdf);

        Http::assertSent(function (Request $request) {
            $body = (string) $request->body();

            return str_contains($request->url(), '/forms/chromium/convert/html')
                && str_contains($body, 'preferCssPageSize')
                && str_contains($body, 'printBackground')
                && str_contains($body, 'Invoice');
        });
    }

    public function test_a_local_image_is_inlined_because_gotenberg_cannot_fetch_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('letterheads/lh.png', 'PNGBYTES');
        $this->fakeGotenberg();

        app(PdfRenderer::class)->fromHtml('<img src="/storage/letterheads/lh.png" alt="Clinic Letterhead">');

        Http::assertSent(function (Request $request) {
            $body = (string) $request->body();

            return str_contains($body, 'data:image/png;base64,' . base64_encode('PNGBYTES'))
                && ! str_contains($body, 'src="/storage/letterheads/lh.png"');
        });
    }

    public function test_a_remote_image_is_left_alone(): void
    {
        $this->fakeGotenberg();

        app(PdfRenderer::class)->fromHtml('<img src="https://example.com/x.png">');

        Http::assertSent(fn (Request $r) => str_contains((string) $r->body(), 'https://example.com/x.png'));
    }

    public function test_a_renderer_failure_is_reported_not_swallowed(): void
    {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('boom', 503)]);

        $this->expectException(\RuntimeException::class);

        app(PdfRenderer::class)->fromHtml('<p>x</p>');
    }
}
