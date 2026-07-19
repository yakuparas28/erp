<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // null: platform varsayılanı
            $table->string('key');   // ör. tenant_admin_invitation
            $table->string('name');  // panelde görünen ad
            $table->string('subject');
            $table->text('body');    // markdown + {{degisken}} yer tutucuları
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
