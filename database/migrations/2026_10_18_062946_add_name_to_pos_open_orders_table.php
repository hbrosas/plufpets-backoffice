<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->string('name')->default('')->after('number');
        });

        DB::table('pos_open_orders')->where('name', '')->update([
            'name' => DB::raw('number'),
        ]);
    }

    public function down(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
