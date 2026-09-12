<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->string('symbol', 8)->after('name')->default('');
            $table->enum('position', ['before', 'after'])->after('symbol')->default('after');
            $table->decimal('rounding', 12, 6)->after('position')->default('0.010000');
            $table->boolean('active')->after('is_functional')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropColumn(['symbol', 'position', 'rounding', 'active']);
        });
    }
};
