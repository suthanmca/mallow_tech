<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = $request->session()->get('merchant_id');
        $merchant = $merchantId === null ? null : Tenant::query()->find($merchantId);

        if ($merchant === null) {
            $request->session()->forget('merchant_id');

            return redirect()->route('login');
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
