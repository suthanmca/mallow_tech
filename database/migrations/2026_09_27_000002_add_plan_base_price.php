<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('base_price_cents')->nullable();
        });

        DB::table('plans')->whereNull('base_price_cents')->update([
            'base_price_cents' => DB::raw('monthly_price_cents'),
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('base_price_cents');
        });
    }
};
