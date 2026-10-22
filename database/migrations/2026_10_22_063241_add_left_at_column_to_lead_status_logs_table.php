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
            $table->timestamp('left_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_status_logs', function (Blueprint $table) {
            $table->dropIndex(['left_at']);
            $table->dropColumn('left_at');
        });
    }
};
