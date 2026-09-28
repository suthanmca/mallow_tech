<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\MerchantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MerchantPortalController extends Controller
{
    public function loginPage(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('merchant_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $merchant = Tenant::query()->where('email', strtolower($credentials['email']))->first();

        if ($merchant === null || ! Hash::check($credentials['password'], $merchant->password)) {
            return back()->withErrors(['email' => 'Those sign-in details did not match an account.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put('merchant_id', $merchant->id);

        return redirect()->intended(route('dashboard'));
    }

    public function registerPage(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('merchant_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:tenants,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $apiKey = Str::random(64);
        $merchant = Tenant::create([
            'name' => $validated['merchant_name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'api_key_hash' => hash('sha256', $apiKey),
        ]);

        $request->session()->regenerate();
        $request->session()->put('merchant_id', $merchant->id);
        $request->session()->flash('new_api_key', $apiKey);

        return redirect()->route('dashboard');
    }

    public function dashboard(Request $request, MerchantDashboard $dashboard): View
    {
        /** @var Tenant $merchant */
        $merchant = $request->attributes->get('merchant');
        $metrics = $dashboard->forMerchant($merchant);
        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        return view('dashboard', [
            'merchant' => $merchant,
            'metrics' => $metrics,
            'customerCount' => $merchant->customers()->count(),
            'activeSubscriptions' => $merchant->customerSubscriptions()->where('active', true)->count(),
            'usageThisMonth' => DB::table('daily_usage')
                ->where('tenant_id', $merchant->id)
                ->whereBetween('usage_date', [$monthStart, $today])
                ->sum('units'),
            'recentInvoices' => $merchant->invoices()->with('customer')->latest()->limit(6)->get(),
            'monthLabel' => now()->format('F Y'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
