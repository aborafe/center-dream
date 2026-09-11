<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->text('note')->nullable()->after('guardian_phone');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('discount_amount');
            $table->decimal('refund_amount', 10, 2)->default(0)->after('cancelled_at');
            $table->string('refund_note')->nullable()->after('refund_amount');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable()->unique()->after('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'refund_amount', 'refund_note']);
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('note');
        });
    }
};
