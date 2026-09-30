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
            // 1. Дропаем старый внешний ключ
            $table->dropForeign(['lead_id']);

            // 2. Дропаем старый индекс
            $table->dropIndex(['lead_id']);

            // 3. Переименовываем колонку
            $table->renameColumn('lead_id', 'amo_lead_id');

            // 4. Добавляем явный индекс для amo_lead_id
            $table->index('amo_lead_id');

            // 5. Вешаем внешний ключ заново на переименованную колонку
            $table->foreign('amo_lead_id')
                  ->references('id')
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
            // Зеркальный откат назад:
            $table->dropForeign(['amo_lead_id']);
            $table->dropIndex(['amo_lead_id']);
            $table->renameColumn('amo_lead_id', 'lead_id');
            $table->index('lead_id');
            $table->foreign('lead_id')
                  ->references('id')
                  ->on('leads')
                  ->cascadeOnDelete();
        });
    }
};
