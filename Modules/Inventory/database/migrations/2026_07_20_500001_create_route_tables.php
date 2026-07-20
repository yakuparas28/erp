<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('route_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger('to_location_id');
            $table->enum('action', ['push', 'pull']);
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->index(['tenant_id', 'route_id', 'sequence'], 'route_rules_tenant_route_seq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_rules');
        Schema::dropIfExists('routes');
    }
};
