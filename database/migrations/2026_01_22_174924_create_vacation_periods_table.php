<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vacation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            
            // Número de periodo (1 para el primer año, 2 para el segundo, etc.)
            $table->integer('year_number');
            
            // Fechas que abarca este año de servicio (Aniversario a Aniversario)
            $table->date('period_start');
            $table->date('period_end');
            
            // Días que le corresponden por ley en este año (ej. 12, 14, etc.)
            $table->decimal('days_entitled', 8, 4);
            
            // Días que ha "ganado" hasta el momento (útil para tu sistema de devengo semanal)
            $table->decimal('days_accrued', 8, 4)->default(0);
            
            // Días que ha gastado de este periodo específico (FIFO)
            $table->decimal('days_taken', 8, 4)->default(0);
            
            // Control de Prima Vacacional
            $table->boolean('is_premium_paid')->default(false);
            $table->timestamp('premium_paid_at')->nullable();
            
            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->unique(['employee_id', 'year_number']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_periods');
    }
};