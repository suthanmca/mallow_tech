<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('monthly_price_cents');
            $table->unsignedBigInteger('included_units');
            $table->unsignedInteger('overage_price_cents');
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key_hash', 64)->unique();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at');
            $table->timestamps();
        });

        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quantity');
            $table->timestamp('occurred_at');
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('usage_units');
            $table->unsignedBigInteger('included_units');
            $table->unsignedBigInteger('overage_units');
            $table->unsignedBigInteger('base_amount_cents');
            $table->unsignedBigInteger('overage_amount_cents');
            $table->unsignedBigInteger('total_amount_cents');
            $table->timestamps();
            $table->unique(['tenant_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
