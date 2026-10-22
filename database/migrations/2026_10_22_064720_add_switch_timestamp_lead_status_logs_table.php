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
            $table->datetime('entered_at')->change();
            $table->datetime('created_at')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {   
        Schema::table('lead_status_logs', function (Blueprint $table) {
            $table->timestamp('entered_at')->change();
            $table->timestamp('created_at')->change();
        });
    }
};
