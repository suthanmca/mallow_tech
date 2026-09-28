<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_merchant_and_signs_into_its_dashboard(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your');

        $this->post('/register', [
            'merchant_name' => 'Northstar Metrics',
            'email' => 'owner@northstar.test',
            'password' => 'strong-password-123',
            'password_confirmation' => 'strong-password-123',
        ])->assertRedirect('/dashboard')->assertSessionHas('merchant_id');

        $merchant = Tenant::query()->where('email', 'owner@northstar.test')->firstOrFail();
        $this->assertTrue(Hash::check('strong-password-123', $merchant->password));
        $this->assertSame(64, strlen(session('new_api_key')));
        $this->get('/dashboard')->assertOk()->assertSee('Northstar Metrics')->assertSee('Your workspace is ready');
    }

    public function test_login_and_logout_manage_the_merchant_session(): void
    {
        $merchant = Tenant::create([
            'name' => 'Cedar Works',
            'email' => 'admin@cedar.test',
            'password' => 'correct-horse-battery',
            'api_key_hash' => hash('sha256', Str::random(64)),
        ]);

        $this->get('/login')->assertOk()->assertSee('Sign in to');
        $this->post('/login', [
            'email' => 'admin@cedar.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->post('/login', [
            'email' => 'admin@cedar.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/dashboard')->assertSessionHas('merchant_id', $merchant->id);
        $this->get('/dashboard')->assertOk()->assertSee('Cedar Works');
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('merchant_id');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_anonymous_visitors_cannot_open_the_merchant_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
