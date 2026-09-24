<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Refuses any upload that a web server or browser could run as code
 * (PHP, HTML, SVG, JS), judged by the client extension, the extension
 * guessed from content, and the detected MIME type. Audit SEC-01 / AUTH-06.
 *
 * Used where the allowed clinical types vary too much for a strict allow-list
 * (STL scans, DICOM, office documents). Keep an allow-list (mimes:) as well
 * wherever the types are known.
 */
class SafeUpload implements ValidationRule
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'js', 'mjs', 'xml', 'xsl',
        'htaccess', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp', 'exe', 'bat',
    ];

    // Exact MIME types, plus anything containing 'php'. Matching the fragment
    // 'xml' used to refuse every .docx/.xlsx/.pptx, whose MIME types contain
    // 'openxmlformats' (found 24 Sep 2026 while wiring 2A.3).
    private const BLOCKED_MIMES = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml', 'image/svg',
        'text/xml', 'application/xml', 'text/javascript', 'application/javascript',
        'application/x-javascript', 'application/ecmascript', 'text/x-shellscript',
        'application/x-sh', 'application/x-httpd-php', 'application/x-msdownload',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $clientName = strtolower($value->getClientOriginalName());
        $segments   = array_slice(explode('.', $clientName), 1); // every extension, so x.php.jpg is caught too
        $guessed    = strtolower((string) $value->guessExtension());
        $mime       = strtolower((string) $value->getMimeType());

        $blocked = array_intersect($segments, self::BLOCKED_EXTENSIONS) !== []
            || in_array($guessed, self::BLOCKED_EXTENSIONS, true)
            || $this->mimeIsBlocked($mime)
            || $this->startsWithScript($value);

        if ($blocked) {
            $fail('This file type is not allowed.');
        }
    }

    private function mimeIsBlocked(string $mime): bool
    {
        return str_contains($mime, 'php') || in_array($mime, self::BLOCKED_MIMES, true);
    }

    private function startsWithScript(UploadedFile $file): bool
    {
        $head = @file_get_contents($file->getRealPath(), false, null, 0, 512);

        return is_string($head) && preg_match('/<\?php|<\?=|<script|<html|<svg/i', $head) === 1;
    }
}
