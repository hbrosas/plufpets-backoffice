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
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->boolean('uses_delivery_schedule')->default(false)->after('asks_for_address');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('delivery_schedule_id')->nullable()->after('delivery_fee')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_schedule_id');
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropColumn('uses_delivery_schedule');
        });
    }
};
