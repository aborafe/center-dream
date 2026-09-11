<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->enum('method', ['cash', 'transfer', 'wallet']);
            $table->string('note')->nullable();
            $table->timestamp('refunded_at')->index();
            $table->timestamps();
            $table->index(['refunded_by', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
