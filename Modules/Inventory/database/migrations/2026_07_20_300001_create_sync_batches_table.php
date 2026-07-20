<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('batch_uuid');
            $table->timestamps();

            // Aynı chunk'ın iki kez işlenmesini DB seviyesinde engeller (PRD 3.3).
            $table->unique(['tenant_id', 'batch_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_batches');
    }
};
