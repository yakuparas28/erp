<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_package_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_package_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();

            $table->unique(['license_package_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_package_modules');
    }
};
