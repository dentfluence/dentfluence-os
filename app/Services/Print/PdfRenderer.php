<?php

declare(strict_types=1);

namespace App\Services\Print;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * V.27 — renders a print Blade to PDF through Gotenberg (headless Chromium).
 *
 * Why this exists: the web printed 9 Blade templates while the app drew its
 * own documents in Dart, so an invoice looked like two different documents
 * depending on where it was printed (measured side by side, 18 Sep 2026).
 * The fix is one renderer: the server turns the SAME Blade the browser prints
 * into a PDF, and every client prints those bytes.
 *
 * 🪤 Chromium runs in its own container. It has no session, no cookies and no
 * access to the app's private storage, so ANY <img src> pointing back at this
 * app would render blank — silently. Every local image is therefore inlined as
 * a base64 data URI before the HTML leaves this process. That single detail is
 * the difference between a letterhead that prints and one that vanishes.
 */
class PdfRenderer
{
    /** Render a Blade view to PDF bytes. */
    public function fromView(string $view, array $data = []): string
    {
        return $this->fromHtml(View::make($view, $data)->render());
    }

    /** Render a complete HTML document to PDF bytes. */
    public function fromHtml(string $html): string
    {
        $html = $this->inlineLocalImages($html);

        $response = Http::timeout(config('pdf.timeout', 30))
            ->asMultipart()
            ->attach('files', $html, 'index.html')
            ->post(rtrim((string) config('pdf.gotenberg_url'), '/') . '/forms/chromium/convert/html', [
                // The Blades set @page themselves; honour that over any default.
                ['name' => 'preferCssPageSize', 'contents' => 'true'],
                // Header bars and table shading are part of the document, not
                // decoration — without this Chromium drops every background.
                ['name' => 'printBackground', 'contents' => 'true'],
                ['name' => 'paperWidth',  'contents' => (string) config('pdf.paper.width', 8.27)],
                ['name' => 'paperHeight', 'contents' => (string) config('pdf.paper.height', 11.69)],
            ]);

        if (! $response->successful()) {
            Log::error('PdfRenderer: gotenberg refused the document', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('Could not produce the PDF (renderer status ' . $response->status() . ').');
        }

        return $response->body();
    }

    /**
     * Replace every local <img src> with a base64 data URI.
     *
     * Handles the three places an image can live in this app: the public disk,
     * the default (private) disk that W-2 moved uploads onto, and a plain file
     * under public/. A remote URL (a data: URI, or an http address that is not
     * this app) is left exactly as it is.
     */
    private function inlineLocalImages(string $html): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc\s*=\s*([\'"])(.*?)\1/i',
            function (array $m) {
                $src = $m[2];

                if (str_starts_with($src, 'data:')) {
                    return $m[0];
                }

                $bytes = $this->readLocal($src);
                if ($bytes === null) {
                    return $m[0];
                }

                $mime = $this->mimeFor($src);

                return str_replace($src, 'data:' . $mime . ';base64,' . base64_encode($bytes), $m[0]);
            },
            $html
        ) ?? $html;
    }

    /** Bytes for a local image reference, or null when it is not ours to read. */
    private function readLocal(string $src): ?string
    {
        $path = $src;

        // Strip our own host, so /storage/x and https://os.../storage/x behave alike.
        if (preg_match('#^https?://#i', $src)) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $srcHost = parse_url($src, PHP_URL_HOST);
            if (! $appHost || ! $srcHost || strcasecmp($appHost, $srcHost) !== 0) {
                return null;   // someone else's image — leave the URL alone
            }
            $path = (string) parse_url($src, PHP_URL_PATH);
        }

        $path     = ltrim($path, '/');
        $relative = str_starts_with($path, 'storage/') ? substr($path, strlen('storage/')) : $path;

        foreach ([['public', $relative], [null, $relative]] as [$disk, $candidate]) {
            try {
                $fs = $disk ? Storage::disk($disk) : Storage::disk();
                if ($fs->exists($candidate)) {
                    return $fs->get($candidate);
                }
            } catch (\Throwable $e) {
                // A misconfigured disk must never break a print.
            }
        }

        $file = public_path($path);

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    private function mimeFor(string $src): string
    {
        return match (strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?: $src, PATHINFO_EXTENSION))) {
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'svg'          => 'image/svg+xml',
            default        => 'image/jpeg',
        };
    }
}
