<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->boolean('asks_for_address')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropColumn('asks_for_address');
        });
    }
};
