<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_sale_id');
        });
    }
};
