<?php

namespace App\Http\Middleware;

use Cartxis\Settings\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class FrontendMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isFrontendRequest($request)) {
            return $next($request);
        }

        $enabled = (bool) Setting::get('system.maintenance_enabled', false);
        if (!$enabled) {
            return $next($request);
        }

        $allowedIps = json_decode((string) Setting::get('system.maintenance_allowed_ips', '[]'), true);
        if (is_array($allowedIps) && in_array($request->ip(), $allowedIps, true)) {
            return $next($request);
        }

        $title = (string) Setting::get('system.maintenance_title', "We'll be back soon!");
        $message = (string) Setting::get('system.maintenance_message', 'We are performing scheduled maintenance.');
        $retryAfter = max(60, (int) Setting::get('system.maintenance_retry_after', 3600));

        // An Inertia browser cannot read a bare HTML card. Handed one, it throws
        // the visitor's page away and shows an error box instead of the message
        // they were supposed to get, which turns "we are closed for an hour"
        // into "your browser is broken". So the notice is sent as an Inertia page
        // when Inertia asked, and as HTML when a plain browser asked.
        if ($request->header('X-Inertia')) {
            $response = Inertia::render('Error', [
                'status' => 503,
                'title' => $title,
                'message' => $message,
                'maintenance' => true,
            ])->toResponse($request);

            $response->setStatusCode(503);
            $response->headers->set('Retry-After', (string) $retryAfter);

            return $response;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'title' => $title,
            ], 503)->header('Retry-After', $retryAfter);
        }

        $content = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($title) . '</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#f8fafc;color:#0f172a;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}.card{max-width:640px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;box-shadow:0 10px 30px rgba(2,6,23,.08)}h1{margin:0 0 10px;font-size:28px}p{margin:0;color:#334155;line-height:1.6}</style>'
            . '</head><body><div class="card"><h1>' . e($title) . '</h1><p>' . e($message) . '</p></div></body></html>';

        return response($content, 503)->header('Retry-After', $retryAfter);
    }

    protected function isFrontendRequest(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return false;
        }

        if ($request->is('api') || $request->is('api/*')) {
            return false;
        }

        return true;
    }
}
