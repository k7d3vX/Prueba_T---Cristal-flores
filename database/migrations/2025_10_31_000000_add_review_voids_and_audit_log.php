<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2: revisión de la dueña, anulación sin borrar y bitácora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->string('review_status')->nullable();          // pending | reviewed | flagged (solo turnos cerrados)
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });

        // Los turnos que ya estaban cerrados quedan pendientes de revisión.
        DB::statement("UPDATE cash_sessions SET review_status = 'pending' WHERE status = 'closed'");

        foreach (['sales', 'withdrawals'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users');
                $table->string('void_reason')->nullable();
                $table->timestamp('void_requested_at')->nullable();   // el cajero pidió anularla
                $table->string('void_request_note')->nullable();
            });
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');   // null = el sistema (seeder)
            $table->unsignedBigInteger('cash_session_id')->nullable()->index();
            $table->string('action');
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        foreach (['withdrawals', 'sales'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('voided_by');
                $table->dropColumn(['voided_at', 'void_reason', 'void_requested_at', 'void_request_note']);
            });
        }

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note']);
        });
    }
};
