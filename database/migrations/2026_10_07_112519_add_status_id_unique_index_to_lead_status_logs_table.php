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
            $table->unique(
                ['amo_lead_id', 'pipeline_id', 'status_id'],
                'lead_pipeline_status_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_status_logs', function (Blueprint $table) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['lead_status_logs']);
            });
        });
    }
};
