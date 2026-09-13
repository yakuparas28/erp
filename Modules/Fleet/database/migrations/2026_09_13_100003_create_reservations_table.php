<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arac_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('aktif_sofor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('proje_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->dateTime('planlanan_alis_at');
            $table->dateTime('planlanan_teslim_at');
            $table->unsignedInteger('alis_km')->nullable();
            $table->integer('alis_km_sapma')->nullable();
            $table->unsignedInteger('teslim_km')->nullable();
            $table->dateTime('talep_tarihi');
            $table->dateTime('alis_tarihi')->nullable();
            $table->dateTime('teslim_basvurusu_tarihi')->nullable();
            $table->dateTime('teslim_tarihi')->nullable();
            $table->enum('onay_durumu', ['Beklemede', 'Onaylandi', 'Reddedildi'])->default('Beklemede');
            $table->text('red_aciklamasi')->nullable();
            $table->boolean('teslim_beyani')->default(false);
            $table->json('teslim_fotograflari')->nullable();
            $table->text('teslim_ariza_aciklamasi')->nullable();
            $table->string('closure_reason', 32)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'onay_durumu', 'arac_id']);
            $table->index('aktif_sofor_id');
            $table->index(['planlanan_alis_at', 'planlanan_teslim_at']);
        });

        Schema::create('reservation_drivers', function (Blueprint $table): void {
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personel_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['reservation_id', 'personel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_drivers');
        Schema::dropIfExists('reservations');
    }
};
