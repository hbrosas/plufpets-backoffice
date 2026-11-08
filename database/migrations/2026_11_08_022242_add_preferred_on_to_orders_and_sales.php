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
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->date('preferred_on')->nullable()->after('delivery_schedule_id');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->date('preferred_on')->nullable()->after('delivery_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn('preferred_on');
        });

        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->dropColumn('preferred_on');
        });
    }
};
