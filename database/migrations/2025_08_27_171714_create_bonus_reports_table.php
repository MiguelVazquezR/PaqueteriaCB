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
        Schema::create('bonus_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('period_date'); // Primer día del mes del reporte (ej. 2025-08-01)
            $table->integer('total_late_minutes')->default(0);
            $table->integer('total_unjustified_absences')->default(0);
            $table->boolean('punctuality_bonus_earned')->default(false);
            $table->boolean('attendance_bonus_earned')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'period_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_reports');
    }
};
