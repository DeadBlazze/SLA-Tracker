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
        Schema::create('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id');
            $table->unsignedInteger('pipeline_id');
            $table->unsignedInteger('status_id');
            $table->unsignedInteger('task_type_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('responsible_user_id');
            $table->boolean('is_completed')->default(false);
            
            // Используем dateTime вместо timestamp
            $table->dateTime('complete_till')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // Составной первичный ключ: предотвращает дубли задач одного типа на этапе
            $table->primary(['lead_id', 'status_id', 'task_type_id']);

            // Индексы для быстрой выборки аналитики и поиска по task_id из вебхуков amo
            $table->index('task_id');
            $table->index('responsible_user_id');
            $table->index(['pipeline_id', 'status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};