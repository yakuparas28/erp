<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arac_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('giris_yapan_id')->constrained('users')->restrictOnDelete();
            $table->enum('islem_turu', ['Bakim', 'Muayene']);
            $table->text('yapilan_islemler');
            $table->text('degisen_parcalar')->nullable();
            $table->date('yeni_bakim_tarihi')->nullable();
            $table->date('yeni_muayene_tarihi')->nullable();
            $table->dateTime('kayit_tarihi');
            $table->timestamps();

            $table->index(['tenant_id', 'arac_id', 'islem_turu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
