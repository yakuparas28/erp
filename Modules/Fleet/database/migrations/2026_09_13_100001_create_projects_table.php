<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ad');
            $table->date('baslangic_tarihi')->nullable();
            $table->text('aciklama')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
