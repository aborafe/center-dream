<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->foreignId('subject_id')->nullable()->after('teacher_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
