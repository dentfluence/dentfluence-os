<?php

namespace App\Services\Smoke;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * HttpProbe — in-process GET requests through the real HTTP kernel.
 *
 * TEST INFRASTRUCTURE ONLY. Lets the smoke suite verify that pages and lazy
 * fragments actually render (full middleware stack, real routes, real views)
 * without a browser or web server — catching "the database is right but the
 * screen 500s". Because the probe runs on the same DB connection as the smoke
 * run, it sees uncommitted rollback-mode data too.
 *
 * The runner logs the smoke actor in once via Auth::login(); Laravel's
 * memoized session guard then authenticates every probe in this process.
 * GET-only by design — probes must never write.
 */
class HttpProbe
{
    /** Substrings that mark a 200 response as actually broken. */
    private const ERROR_MARKERS = [
        'SQLSTATE[',
        'Stack trace:',
        'Whoops, looks like something went wrong',
        'ErrorException',
    ];

    /**
     * @return array{status:int, ok:bool, error:?string, body:string}
     */
    public function get(string $url): array
    {
        try {
            $request = Request::create($url, 'GET');
            $request->headers->set('Accept', 'text/html');

            $response = app(HttpKernel::class)->handle($request);

            $status = $response->getStatusCode();
            $body   = (string) $response->getContent();
            $error  = null;

            if ($status >= 500) {
                $error = "HTTP {$status}";
            } elseif ($status === 404) {
                $error = 'HTTP 404';
            } elseif (in_array($status, [301, 302], true)) {
                $to    = (string) $response->headers->get('Location');
                $error = str_contains($to, '/login')
                    ? 'bounced to login (probe not authenticated?)'
                    : "unexpected redirect to {$to}";
            } elseif ($status === 200) {
                foreach (self::ERROR_MARKERS as $marker) {
                    if (str_contains($body, $marker)) {
                        $error = "exception marker \"{$marker}\" in 200 body";
                        break;
                    }
                }
            }

            return [
                'status' => $status,
                'ok'     => $error === null && $status === 200,
                'error'  => $error,
                'body'   => $body,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'ok'     => false,
                'error'  => $e::class . ': ' . $e->getMessage(),
                'body'   => '',
            ];
        }
    }

    /** True when the URL renders 200 AND the body contains $needle. */
    public function sees(string $url, string $needle): bool
    {
        $r = $this->get($url);

        return $r['ok'] && str_contains($r['body'], $needle);
    }
}
