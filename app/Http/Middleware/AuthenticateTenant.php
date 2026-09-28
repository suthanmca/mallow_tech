<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            return response()->json(['message' => 'A valid API key is required.'], 401);
        }

        $tenant = Tenant::query()
            ->where('api_key_hash', hash('sha256', $apiKey))
            ->first();

        if ($tenant === null) {
            return response()->json(['message' => 'A valid API key is required.'], 401);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
