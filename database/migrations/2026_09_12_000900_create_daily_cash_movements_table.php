<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['income', 'expense'])->index();
            $table->string('category', 40);
            $table->decimal('amount', 12, 2)->unsigned();
            $table->date('movement_date')->index();
            $table->foreignId('collector_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['movement_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_cash_movements');
    }
};
