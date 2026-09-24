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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amo_lead_id')->unique();
            $table->unsignedBigInteger('pipeline_id')->index();
            $table->unsignedBigInteger('status_id')->index();
            $table->unsignedBigInteger('old_status_id')->nullable();
            $table->string('source_phone', 32)->nullable();
            $table->string('promo_source', 255)->nullable();
            $table->unsignedBigInteger('promo_source_enum_id')->nullable();
            $table->unsignedBigInteger('amo_source_id')->nullable();
            $table->string('amo_source_name', 255)->nullable();
            $table->decimal('net_profit', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
