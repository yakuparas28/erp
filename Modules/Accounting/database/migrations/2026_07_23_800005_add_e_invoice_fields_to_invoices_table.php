<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('e_invoice_type', ['e_fatura', 'e_arsiv', 'kagit'])->default('kagit');
            $table->enum('e_invoice_status', ['not_sent', 'sent', 'accepted', 'rejected'])->default('not_sent');
            $table->string('gib_uuid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['e_invoice_type', 'e_invoice_status', 'gib_uuid']);
        });
    }
};
