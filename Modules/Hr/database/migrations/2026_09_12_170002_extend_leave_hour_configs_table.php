<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_hour_configs', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('department_id');
            $table->decimal('min_hours', 4, 2)->default(1)->after('monthly_leave_hours');
            $table->enum('negative_balance_policy', ['strict', 'lenient'])->default('strict')->after('min_hours');
        });
    }

    public function down(): void
    {
        Schema::table('leave_hour_configs', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'min_hours', 'negative_balance_policy']);
        });
    }
};
