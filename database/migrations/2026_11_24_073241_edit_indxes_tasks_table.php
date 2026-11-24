<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_analytics_index');
            $table->index(['lead_id', 'pipeline_id', 'task_type_id', 'status_id'], 'tasks_analytics_index');
        });
    }

    public function down(): void
    {
        //
    }
};
