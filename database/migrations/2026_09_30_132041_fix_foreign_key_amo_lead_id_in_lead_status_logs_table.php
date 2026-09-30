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
        Schema::table('lead_status_logs', function (Blueprint $table) {
            // Дропаем текущий foreign key, ссылающийся на leads(id)
            $table->dropForeign(['amo_lead_id']);

            // Вешаем правильную связь: amo_lead_id -> leads(amo_lead_id)
            $table->foreign('amo_lead_id')
                  ->references('amo_lead_id')
                  ->on('leads')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_status_logs', function (Blueprint $table) {
            // Откат обратно на leads(id)
            $table->dropForeign(['amo_lead_id']);

            $table->foreign('amo_lead_id')
                  ->references('id')
                  ->on('leads')
                  ->cascadeOnDelete();
        });
    }
};
