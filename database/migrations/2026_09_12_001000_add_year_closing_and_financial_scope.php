<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->timestamp('academic_closed_at')->nullable()->after('is_active');
            $table->foreignId('academic_closed_by')->nullable()->after('academic_closed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('financial_closed_at')->nullable()->after('academic_closed_by');
            $table->foreignId('financial_closed_by')->nullable()->after('financial_closed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->unique(['academic_year_id', 'phone']);
        });

        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->foreignId('academic_year_id')->nullable()->after('teacher_id')->constrained()->nullOnDelete();
            $table->index(['academic_year_id', 'paid_at']);
        });

        Schema::table('daily_cash_movements', function (Blueprint $table): void {
            $table->foreignId('academic_year_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['academic_year_id', 'movement_date']);
        });

        $activeYearId = DB::table('academic_years')->where('is_active', true)->value('id');

        if ($activeYearId) {
            DB::table('daily_cash_movements')->whereNull('academic_year_id')->update(['academic_year_id' => $activeYearId]);
            DB::table('teacher_payouts')->whereNull('academic_year_id')->update(['academic_year_id' => $activeYearId]);
        }

        DB::table('teacher_payouts')
            ->whereNotNull('subject_id')
            ->orderBy('id')
            ->each(function (object $payout): void {
                $yearId = DB::table('subjects')->where('id', $payout->subject_id)->value('academic_year_id');

                if ($yearId) {
                    DB::table('teacher_payouts')->where('id', $payout->id)->update(['academic_year_id' => $yearId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('daily_cash_movements', function (Blueprint $table): void {
            $table->dropForeign(['academic_year_id']);
            $table->dropIndex(['academic_year_id', 'movement_date']);
            $table->dropColumn('academic_year_id');
        });

        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->dropForeign(['academic_year_id']);
            $table->dropIndex(['academic_year_id', 'paid_at']);
            $table->dropColumn('academic_year_id');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['academic_year_id', 'phone']);
            $table->unique('phone');
        });

        Schema::table('academic_years', function (Blueprint $table): void {
            $table->dropForeign(['academic_closed_by']);
            $table->dropForeign(['financial_closed_by']);
            $table->dropColumn(['academic_closed_at', 'academic_closed_by', 'financial_closed_at', 'financial_closed_by']);
        });
    }
};
