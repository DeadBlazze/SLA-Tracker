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
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropPrimary(['lead_id', 'status_id', 'task_type_id']);
            $table->dropIndex(['task_id']); 

            $table->primary('task_id');

            $table->index(
                ['lead_id', 'status_id', 'pipeline_id', 'task_type_id'], 
                'tasks_analytics_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_analytics_index');
            $table->dropPrimary(['task_id']);

            $table->index('task_id');
            $table->primary(['lead_id', 'status_id', 'task_type_id']);
        });
    }
};