<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 (roles): apertura en dos pasos y fondo habitual por caja.
 * - cash_registers.default_float: el fondo que la dueña fijó para esa caja.
 * - cash_sessions.float_confirmed_at / float_disputed_at: el cajero confirma (o discute) el fondo recibido.
 * - Un turno "activo" es open o pending_confirmation: máximo uno por caja y uno por cajero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->decimal('default_float', 12, 2)->default('300.00');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->timestamp('float_confirmed_at')->nullable();
            $table->timestamp('float_disputed_at')->nullable();
        });

        // Los turnos que ya estaban abiertos se consideran confirmados.
        DB::statement("UPDATE cash_sessions SET float_confirmed_at = opened_at WHERE status = 'open'");

        DB::statement('DROP INDEX cash_sessions_one_open_per_register');
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_active_per_register ON cash_sessions (cash_register_id) WHERE status IN ('open', 'pending_confirmation')");
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_active_per_cashier ON cash_sessions (cashier_id) WHERE status IN ('open', 'pending_confirmation')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX cash_sessions_one_active_per_cashier');
        DB::statement('DROP INDEX cash_sessions_one_active_per_register');
        DB::statement("UPDATE cash_sessions SET status = 'open' WHERE status = 'pending_confirmation'");
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON cash_sessions (cash_register_id) WHERE status = 'open'");

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn(['float_confirmed_at', 'float_disputed_at']);
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('default_float');
        });
    }
};
