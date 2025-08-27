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
        Schema::table('holiday_rules', function (Blueprint $table) {
            // Añadimos el estado y removemos branch_id que ya no se necesita
            $table->boolean('is_active')->default(true)->after('rule_definition');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        // Creamos la tabla pivote
        Schema::create('branch_holiday_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('holiday_rule_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_holiday_rule');
        Schema::table('holiday_rules', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
