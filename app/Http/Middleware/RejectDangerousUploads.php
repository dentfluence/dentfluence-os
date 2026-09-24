<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Rules\SafeUpload;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * One choke point for every upload in the app (Signal Board 2A.3, audit
 * AUTH-06): any file a web server or browser could run as code - PHP, HTML,
 * SVG, JS, including a .php renamed .jpg - is refused before any controller
 * sees it. Endpoints keep their own allow-lists (images/PDF/STL/office docs);
 * this guard makes sure no endpoint, present or future, can forget the
 * dangerous cases.
 */
class RejectDangerousUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        $files = $request->allFiles();
        if ($files === []) {
            return $next($request);
        }

        $rule = new SafeUpload();
        foreach ($this->flatten($files) as $field => $file) {
            $failed = false;
            $rule->validate($field, $file, function () use (&$failed) { $failed = true; });

            if ($failed) {
                AuditLog::event('upload_refused', auth()->id(), [
                    'field'    => $field,
                    'filename' => mb_substr($file->getClientOriginalName(), 0, 120),
                    'path'     => $request->path(),
                ], ['module' => 'security']);

                $message = 'This file type is not allowed.';
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['success' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422);
                }

                return back()->withErrors([$field => $message])->withInput($request->except(array_keys($files)));
            }
        }

        return $next($request);
    }

    /** @return array<string, UploadedFile> */
    private function flatten(array $files, string $prefix = ''): array
    {
        $out = [];
        foreach ($files as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if ($value instanceof UploadedFile) {
                $out[$name] = $value;
            } elseif (is_array($value)) {
                $out += $this->flatten($value, $name);
            }
        }

        return $out;
    }
}
