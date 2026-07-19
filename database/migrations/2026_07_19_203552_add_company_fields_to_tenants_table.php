<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('accounting_mode');
            $table->string('tax_office')->nullable()->after('tax_number');
            $table->string('email')->nullable()->after('tax_office');
            $table->string('phone')->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['tax_number', 'tax_office', 'email', 'phone', 'address']);
        });
    }
};
