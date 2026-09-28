<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique('plans_name_unique');
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle', 16)->default('monthly')->after('currency');
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'external_id']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('billing_cycle', 16);
            $table->timestamp('started_at');
            $table->timestamp('current_period_start');
            $table->timestamp('next_billing_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'active', 'next_billing_at']);
        });

        Schema::create('subscription_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('included_units');
            $table->unsignedBigInteger('overage_price_cents');
            $table->timestamps();
            $table->index(['customer_subscription_id', 'starts_at', 'ends_at']);
        });

        Schema::table('usage_events', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('aggregated_at')->nullable();
            $table->index(['tenant_id', 'customer_id', 'occurred_at']);
            $table->index(['subscription_segment_id', 'occurred_at']);
        });

        Schema::create('daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('units')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'customer_id', 'subscription_segment_id', 'usage_date']);
            $table->index(['tenant_id', 'usage_date', 'customer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_id_period_start_unique');
            $table->foreignId('customer_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('issued');
            $table->unique(['customer_id', 'period_start']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('usage_units');
            $table->unsignedBigInteger('included_units');
            $table->unsignedBigInteger('overage_units');
            $table->unsignedBigInteger('base_amount_cents');
            $table->unsignedBigInteger('overage_amount_cents');
            $table->timestamps();
            $table->unique(['invoice_id', 'subscription_segment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_customer_id_period_start_unique');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('status');
            $table->unique(['tenant_id', 'period_start']);
        });
        Schema::dropIfExists('daily_usage');
        Schema::table('usage_events', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'customer_id', 'occurred_at']);
            $table->dropIndex(['subscription_segment_id', 'occurred_at']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('subscription_segment_id');
            $table->dropColumn('aggregated_at');
        });
        Schema::dropIfExists('subscription_segments');
        Schema::dropIfExists('customer_subscriptions');
        Schema::dropIfExists('customers');
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('billing_cycle');
            $table->unique('name');
        });
    }
};
