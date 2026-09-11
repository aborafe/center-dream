<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('fee', 12, 2)->unsigned();
            $table->decimal('discount_amount', 12, 2)->unsigned()->default(0);
            $table->timestamps();
            $table->unique(['student_id', 'subject_id']);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['amount', 'percentage']);
            $table->decimal('value', 12, 2)->unsigned();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->enum('method', ['cash', 'transfer', 'wallet']);
            $table->string('receipt_number')->unique();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
            $table->index(['received_by', 'paid_at']);
        });

        Schema::create('teacher_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->date('period_from');
            $table->date('period_to');
            $table->enum('method', ['cash', 'transfer', 'wallet']);
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
            $table->index(['teacher_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_payouts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('enrollments');
    }
};
