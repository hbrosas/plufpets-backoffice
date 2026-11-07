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
        Schema::table('branch_inventories', function (Blueprint $table) {
            $table->unsignedInteger('reserved')->default(0)->after('quantity');
        });

        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->string('inventory_hold')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_inventories', function (Blueprint $table) {
            $table->dropColumn('reserved');
        });

        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->dropColumn('inventory_hold');
        });
    }
};
