<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('critical_dates', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('block_leave_requests')->constrained('users')->nullOnDelete();
            $table->enum('scope', ['tenant_wide', 'departments'])->default('tenant_wide')->after('created_by');
        });

        Schema::create('critical_date_departments', function (Blueprint $table): void {
            $table->foreignId('critical_date_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['critical_date_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_date_departments');
        Schema::table('critical_dates', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'scope']);
        });
    }
};
