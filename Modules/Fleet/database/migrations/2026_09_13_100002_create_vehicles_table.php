<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plaka', 32);
            $table->string('marka_model');
            $table->unsignedSmallInteger('yil');
            $table->string('sasi_no', 64)->nullable();
            $table->string('motor_no', 64)->nullable();
            $table->unsignedInteger('guncel_km')->default(0);
            $table->date('bakim_tarihi')->nullable();
            $table->date('muayene_tarihi')->nullable();
            $table->date('bakim_uyari_son_gonderim')->nullable();
            $table->date('muayene_uyari_son_gonderim')->nullable();
            $table->date('mtv_odeme_tarihi')->nullable();
            $table->enum('mtv_odeme_durumu', ['Odendi', 'Odenmedi', 'Kismi_Odendi'])->default('Odenmedi');
            $table->text('mtv_odeme_notu')->nullable();
            $table->date('mtv_uyari_son_gonderim')->nullable();
            $table->enum('durum', ['Garajda', 'Aktif_Kullanimda', 'Blokeli_Bakimda'])->default('Garajda');
            $table->timestamps();

            $table->unique(['tenant_id', 'plaka']);
            $table->index(['tenant_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
