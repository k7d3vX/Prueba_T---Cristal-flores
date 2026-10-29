<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cajas físicas de la tienda (puede haber más de una).
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Un turno/sesión de una caja: abre con un cajero y un fondo, y se cierra.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('opened_by')->constrained('users');
            $table->decimal('opening_float', 12, 2);
            $table->string('closing_mode');                 // normal | blind
            $table->string('status')->default('open');      // open | closed
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            // Snapshots guardados al cerrar (auditoría, RN-8)
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->timestamps();
        });

        // Red de seguridad: PostgreSQL impide dos sesiones abiertas en la MISMA caja (AC-01).
        // Cajas distintas sí pueden estar abiertas a la vez.
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON cash_sessions (cash_register_id) WHERE status = 'open'");

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method');                       // cash | qr
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
