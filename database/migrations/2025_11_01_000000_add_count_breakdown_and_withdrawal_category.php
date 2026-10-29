<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fase 3: conteo por denominaciones y categoría de la salida. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->json('count_breakdown')->nullable();   // {"b200": 4, "b100": 7, ...}
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('category')->default('other');  // supplier | services | advance | other
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('cash_sessions', fn (Blueprint $table) => $table->dropColumn('count_breakdown'));
    }
};
