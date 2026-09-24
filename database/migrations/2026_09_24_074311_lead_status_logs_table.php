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
        Schema::create('lead_status_logs', function (Blueprint $table) {
            $table->id();

            // 1. Создаем само поле (колонка ребенка)
            $table->unsignedBigInteger('lead_id');

            // 2. Явный индекс для ускорения выборок
            $table->index('lead_id');

            // 3. Внешний ключ: ребенок -> id таблицы leads с каскадным удалением
            $table->foreign('lead_id')
                  ->references('id')
                  ->on('leads')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('pipeline_id')->index();
            $table->unsignedBigInteger('old_status_id')->nullable();
            $table->unsignedBigInteger('status_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->timestamp('entered_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_status_logs');
    }
};
