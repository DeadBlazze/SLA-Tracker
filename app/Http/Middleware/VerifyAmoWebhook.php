<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAmoWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('services.amocrm.webhook_secret');
        
        $providedSecret = $request->query('secret');

        if (!hash_equals($expectedSecret, (string) $providedSecret)) {
            abort(403, 'Unauthorized Webhook');
        }

        return $next($request);
    }
}
