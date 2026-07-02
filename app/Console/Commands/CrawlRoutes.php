<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route as RouteFacade;
use GuzzleHttp\Cookie\CookieJar;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  app:crawl-routes  —  Automatic "third-party" page tester for Dentfluence
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT IT DOES (in plain language):
 *  It pretends to be a real user. It logs in with an email + password,
 *  then visits EVERY page (GET route) in the app one by one. For each page
 *  it checks whether the page:
 *     - crashed (server error 500)
 *     - is missing (404)
 *     - bounced you back to login (you weren't allowed in)
 *     - came back blank or suspiciously short (a broken / empty view)
 *     - has cut-off / truncated HTML (missing </body> or </html>)
 *     - shows a PHP warning (Undefined variable, etc.)
 *
 *  It then writes a colour-coded HTML report you can open in your browser.
 *
 *  IT IS SAFE: it only opens pages (GET). It never submits forms, never
 *  deletes, and it skips the logout link so the session stays alive.
 *
 *  HOW TO RUN (examples at the bottom of this file / in chat).
 */
class CrawlRoutes extends Command
{
    /** The command name + the options you can pass. */
    protected $signature = 'app:crawl-routes
        {--url= : Base URL of the running app, e.g. http://dentfluence.test}
        {--email= : Admin login email}
        {--password= : Admin login password}
        {--with-ids : Also test pages that need an ID (e.g. /patients/{id}) using --id}
        {--id=1 : The record ID to plug into pages that need one}
        {--timeout=20 : Seconds to wait per page before giving up}';

    protected $description = 'Logs in and visits every page, then writes a broken-page report.';

    /** Shared cookie jar so the login session carries across every request. */
    private CookieJar $jar;

    public function handle(): int
    {
        // ── 1. Work out the base URL ───────────────────────────────────────
        $base = rtrim($this->option('url') ?: env('CRAWL_URL') ?: config('app.url'), '/');
        $email = $this->option('email') ?: env('CRAWL_EMAIL');
        $password = $this->option('password') ?: env('CRAWL_PASSWORD');
        $timeout = (int) $this->option('timeout');

        if (!$email || !$password) {
            $this->error('Missing login. Pass --email= and --password= (or set CRAWL_EMAIL / CRAWL_PASSWORD in .env).');
            return self::FAILURE;
        }

        $this->info("Target app : {$base}");
        $this->info("Logging in : {$email}");

        $this->jar = new CookieJar();

        // ── 2. Log in like a real user ─────────────────────────────────────
        if (!$this->login($base, $email, $password, $timeout)) {
            $this->error('Login failed. Check the URL and credentials, then try again.');
            return self::FAILURE;
        }
        $this->info('Login OK. Collecting pages to test...');

        // ── 3. Collect every GET page worth testing ────────────────────────
        $targets = $this->collectRoutes($base);
        $this->info('Found ' . count($targets) . ' pages to visit.');
        $this->newLine();

        // ── 4. Visit each page and grade it ────────────────────────────────
        $results = [];
        $bar = $this->output->createProgressBar(count($targets));
        $bar->start();

        foreach ($targets as $t) {
            $results[] = $this->visit($base, $t, $timeout);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        // ── 5. Write the report + print a summary ──────────────────────────
        $reportPath = $this->writeReport($base, $results);
        $this->printSummary($results, $reportPath);

        return self::SUCCESS;
    }

    /**
     * Logs in by grabbing the CSRF token from the login page, then POSTing
     * the credentials. Returns true if we end up authenticated.
     */
    private function login(string $base, string $email, string $password, int $timeout): bool
    {
        try {
            $loginPage = Http::withOptions(['cookies' => $this->jar, 'timeout' => $timeout])
                ->get($base . '/login');

            // Pull the hidden _token value out of the login form.
            preg_match('/name="_token"\s+value="([^"]+)"/', $loginPage->body(), $m);
            $token = $m[1] ?? null;
            if (!$token) {
                $this->warn('Could not find the CSRF token on /login.');
                return false;
            }

            $resp = Http::withOptions([
                'cookies' => $this->jar,
                'timeout' => $timeout,
                'allow_redirects' => false, // we want to SEE the redirect
            ])->asForm()->post($base . '/login', [
                '_token'   => $token,
                'email'    => $email,
                'password' => $password,
            ]);

            // A good login redirects (302) somewhere that is NOT /login again.
            $location = $resp->header('Location');
            return $resp->status() === 302 && !str_contains((string) $location, '/login');
        } catch (\Throwable $e) {
            $this->warn('Login request error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Builds the list of pages to visit from Laravel's own route table,
     * so we never have to hard-code 400+ URLs.
     */
    private function collectRoutes(string $base): array
    {
        $withIds = (bool) $this->option('with-ids');
        $id = $this->option('id');

        // Pages we must NOT visit (would log us out, loop, or are noise).
        $skip = ['logout', 'login', 'login.post', 'telescope', 'horizon', 'sanctum'];
        $skipUriContains = ['logout', 'telescope', '_debugbar', 'horizon', 'livewire'];

        $targets = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (!in_array('GET', $route->methods())) {
                continue;
            }
            $uri  = $route->uri();
            $name = $route->getName() ?? '';

            if ($name && in_array($name, $skip)) {
                continue;
            }
            foreach ($skipUriContains as $needle) {
                if (str_contains($uri, $needle)) {
                    continue 2;
                }
            }

            $hasParams = str_contains($uri, '{');

            if ($hasParams) {
                if (!$withIds) {
                    continue; // skip ID pages unless asked
                }
                // Replace every {param} and {param?} with the chosen id.
                $uri = preg_replace('/\{[^}]+\}/', $id, $uri);
            }

            $targets[] = [
                'name'      => $name ?: '(unnamed)',
                'uri'       => '/' . ltrim($uri, '/'),
                'url'       => $base . '/' . ltrim($uri, '/'),
                'has_param' => $hasParams,
            ];
        }

        // De-duplicate by final URL.
        $seen = [];
        return array_values(array_filter($targets, function ($t) use (&$seen) {
            if (isset($seen[$t['url']])) {
                return false;
            }
            $seen[$t['url']] = true;
            return true;
        }));
    }

    /**
     * Visits one page and grades it. Returns a result row.
     */
    private function visit(string $base, array $t, int $timeout): array
    {
        $start = microtime(true);
        $flags = [];
        $level = 'ok';   // ok | warn | error
        $status = 0;
        $bytes = 0;

        try {
            $resp = Http::withOptions([
                'cookies' => $this->jar,
                'timeout' => $timeout,
                'allow_redirects' => false,
            ])->get($t['url']);

            $status = $resp->status();
            $body   = $resp->body();
            $bytes  = strlen($body);

            // --- Grade it ---
            if ($status >= 500) {
                $level = 'error';
                $flags[] = 'Server error (' . $status . ')';
            } elseif ($status === 404) {
                $level = 'error';
                $flags[] = 'Page not found (404)';
            } elseif ($status === 302 || $status === 301) {
                $loc = (string) $resp->header('Location');
                if (str_contains($loc, '/login')) {
                    $level = 'warn';
                    $flags[] = 'Bounced to login (no access)';
                } else {
                    $flags[] = 'Redirect → ' . $loc;
                }
            } elseif ($status === 200) {
                // Look inside the HTML for trouble signs.
                if ($this->looksLikeException($body)) {
                    $level = 'error';
                    $flags[] = 'Exception page rendered';
                }
                if ($bytes < 500) {
                    $level = $level === 'error' ? 'error' : 'warn';
                    $flags[] = 'Blank / very short (' . $bytes . ' bytes)';
                }
                if ($this->looksTruncated($body)) {
                    $level = $level === 'error' ? 'error' : 'warn';
                    $flags[] = 'Truncated HTML (missing closing tag)';
                }
                if ($php = $this->phpWarning($body)) {
                    $level = $level === 'error' ? 'error' : 'warn';
                    $flags[] = 'PHP notice: ' . $php;
                }
            } else {
                $flags[] = 'Status ' . $status;
            }
        } catch (\Throwable $e) {
            $level = 'error';
            $flags[] = 'Request failed: ' . substr($e->getMessage(), 0, 120);
        }

        return [
            'name'   => $t['name'],
            'uri'    => $t['uri'],
            'param'  => $t['has_param'],
            'status' => $status,
            'ms'     => (int) ((microtime(true) - $start) * 1000),
            'bytes'  => $bytes,
            'level'  => $level,
            'flags'  => $flags ?: ['OK'],
        ];
    }

    /** Detects a Laravel/Symfony error page in the HTML body. */
    private function looksLikeException(string $body): bool
    {
        foreach ([
            'Whoops, looks like something went wrong',
            'Symfony\\Component\\ErrorHandler',
            'Stack trace:',
            'SQLSTATE[',
            '<title>Server Error',
            'class="exception"',
        ] as $needle) {
            if (str_contains($body, $needle)) {
                return true;
            }
        }
        return false;
    }

    /** Detects cut-off HTML (opened <body>/<html> but never closed it). */
    private function looksTruncated(string $body): bool
    {
        $hasHtmlOpen = stripos($body, '<html') !== false;
        $hasBodyOpen = stripos($body, '<body') !== false;
        if ($hasHtmlOpen && stripos($body, '</html>') === false) {
            return true;
        }
        if ($hasBodyOpen && stripos($body, '</body>') === false) {
            return true;
        }
        return false;
    }

    /** Detects common PHP warnings leaking into the page. */
    private function phpWarning(string $body): ?string
    {
        foreach ([
            'Undefined variable',
            'Undefined array key',
            'Trying to access array offset',
            'foreach() argument must be',
            'Attempt to read property',
            'htmlspecialchars(): ',
        ] as $needle) {
            if (str_contains($body, $needle)) {
                return $needle;
            }
        }
        return null;
    }

    /**
     * Writes the colour-coded HTML report to storage and returns its path.
     */
    private function writeReport(string $base, array $results): string
    {
        // Sort: errors first, then warnings, then OK.
        $rank = ['error' => 0, 'warn' => 1, 'ok' => 2];
        usort($results, fn($a, $b) => $rank[$a['level']] <=> $rank[$b['level']]);

        $errors = collect($results)->where('level', 'error')->count();
        $warns  = collect($results)->where('level', 'warn')->count();
        $oks    = collect($results)->where('level', 'ok')->count();
        $when   = now()->format('Y-m-d H:i:s');

        $rows = '';
        foreach ($results as $r) {
            $color = ['error' => '#fde2e1', 'warn' => '#fff4d6', 'ok' => '#e6f7ed'][$r['level']];
            $badge = ['error' => '🔴 ERROR', 'warn' => '🟡 WARN', 'ok' => '🟢 OK'][$r['level']];
            $flags = htmlspecialchars(implode(' · ', $r['flags']));
            $rows .= sprintf(
                '<tr style="background:%s"><td>%s</td><td><code>%s</code></td><td>%s</td>'
                . '<td style="text-align:right">%s</td><td style="text-align:right">%d ms</td>'
                . '<td style="text-align:right">%s</td><td>%s</td></tr>',
                $color,
                $badge,
                htmlspecialchars($r['uri']),
                htmlspecialchars($r['name']),
                $r['status'] ?: '—',
                $r['ms'],
                number_format($r['bytes']),
                $flags
            );
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<title>Dentfluence — Page Health Report</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0;background:#f5f6f8;color:#1f2937}
  header{background:#0d3b66;color:#fff;padding:20px 28px}
  header h1{margin:0;font-size:20px}
  header p{margin:4px 0 0;opacity:.85;font-size:13px}
  .cards{display:flex;gap:14px;padding:20px 28px}
  .card{flex:1;background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
  .card .n{font-size:30px;font-weight:700}
  .card.err .n{color:#c0392b}.card.warn .n{color:#b8860b}.card.ok .n{color:#1e8449}
  .card .l{font-size:13px;color:#6b7280;margin-top:2px}
  table{width:calc(100% - 56px);margin:8px 28px 40px;border-collapse:collapse;background:#fff;
        border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
  th,td{padding:9px 12px;font-size:13px;text-align:left;border-bottom:1px solid #eef0f3}
  th{background:#11459a;color:#fff;position:sticky;top:0;cursor:pointer}
  code{font-size:12px;color:#0d3b66}
  .hint{padding:0 28px 24px;color:#6b7280;font-size:12px}
</style></head>
<body>
<header>
  <h1>Dentfluence — Page Health Report</h1>
  <p>{$base} &nbsp;·&nbsp; generated {$when}</p>
</header>
<div class="cards">
  <div class="card err"><div class="n">{$errors}</div><div class="l">Broken pages (fix first)</div></div>
  <div class="card warn"><div class="n">{$warns}</div><div class="l">Warnings (blank / truncated / no access)</div></div>
  <div class="card ok"><div class="n">{$oks}</div><div class="l">Healthy pages</div></div>
</div>
<table id="t">
  <thead><tr>
    <th onclick="sortBy(0)">Result</th><th onclick="sortBy(1)">Page (URL)</th>
    <th onclick="sortBy(2)">Route name</th><th onclick="sortBy(3)">HTTP</th>
    <th onclick="sortBy(4)">Time</th><th onclick="sortBy(5)">Size</th><th>What's wrong</th>
  </tr></thead>
  <tbody>{$rows}</tbody>
</table>
<p class="hint">Click any column header to sort. Rows are sorted worst-first by default.
Tip: re-run after fixing to watch the red number drop.</p>
<script>
function sortBy(i){
  const tb=document.querySelector('#t tbody');
  const rows=[...tb.rows];
  const asc=tb.getAttribute('data-c')!=i+'';
  rows.sort((a,b)=>{
    const x=a.cells[i].innerText, y=b.cells[i].innerText;
    const nx=parseFloat(x), ny=parseFloat(y);
    if(!isNaN(nx)&&!isNaN(ny)) return asc?nx-ny:ny-nx;
    return asc?x.localeCompare(y):y.localeCompare(x);
  });
  rows.forEach(r=>tb.appendChild(r));
  tb.setAttribute('data-c',asc?i:'-1');
}
</script>
</body></html>
HTML;

        $dir = storage_path('app/route-reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/report-' . now()->format('Ymd-His') . '.html';
        file_put_contents($path, $html);

        return $path;
    }

    /** Prints a short summary to the terminal. */
    private function printSummary(array $results, string $reportPath): void
    {
        $errors = collect($results)->where('level', 'error');
        $warns  = collect($results)->where('level', 'warn');

        $this->line('<fg=red>● ' . $errors->count() . ' broken</> '
            . '<fg=yellow>● ' . $warns->count() . ' warnings</> '
            . '<fg=green>● ' . collect($results)->where('level', 'ok')->count() . ' healthy</>');
        $this->newLine();

        if ($errors->count()) {
            $this->error('BROKEN PAGES (fix these first):');
            foreach ($errors->take(25) as $r) {
                $this->line('  ' . $r['uri'] . '  —  ' . implode(', ', $r['flags']));
            }
            $this->newLine();
        }

        $this->info('Full report saved to:');
        $this->line('  ' . $reportPath);
        $this->comment('Open that .html file in your browser to see everything.');
    }
}
