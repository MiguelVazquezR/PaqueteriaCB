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
        Schema::create('concrete_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_rule_id')->constrained('holiday_rules')->cascadeOnDelete();
            $table->date('date');
            $table->unique(['holiday_rule_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concrete_holidays');
    }
};
