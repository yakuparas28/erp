<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // null: platform (Süper Admin) ayarı
            $table->string('host');
            $table->unsignedInteger('port')->default(587);
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted cast
            $table->enum('encryption', ['tls', 'ssl', 'none'])->default('tls');
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
