<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_settings', function (Blueprint $table) {
            $table->id();
            $table->string('center_name');
            $table->string('center_phone', 24);
            $table->string('address');
            $table->string('currency', 32)->default('الجنيه المصري (ج.م)');
            $table->boolean('balance_alerts')->default(true);
            $table->boolean('daily_summary')->default(true);
            $table->boolean('daily_report_copy')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_settings');
    }
};
