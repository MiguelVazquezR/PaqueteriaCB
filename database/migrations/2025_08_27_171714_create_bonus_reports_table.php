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
        // Tabla para el reporte general del mes
        Schema::create('bonus_reports', function (Blueprint $table) {
            $table->id();
            $table->date('period'); // Primer día del mes del reporte, ej: 2025-08-01
            $table->string('status')->default('draft'); // draft, finalized
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        // Tabla para el desglose de bonos por empleado en cada reporte
        Schema::create('bonus_report_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_report_id')->constrained('bonus_reports')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('bonus_id')->constrained('bonuses');
            $table->decimal('calculated_amount', 10, 2)->default(0);
            $table->json('calculation_details')->nullable(); // Para auditoría, ej: { "lates": 1, "absences": 0 }
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_report_details');
        Schema::dropIfExists('bonus_reports');
    }
};