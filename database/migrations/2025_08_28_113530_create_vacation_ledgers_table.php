<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla para el historial detallado de vacaciones
        Schema::create('vacation_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('type', ['initial', 'earned', 'taken', 'adjustment']);
            $table->decimal('days', 8, 2); // Positivo para días ganados, negativo para tomados
            $table->decimal('balance', 8, 2); // El saldo después de esta transacción
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Columna para el saldo actual en la tabla de empleados
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('vacation_balance', 8, 2)->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_ledgers');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('vacation_balance');
        });
    }
};
