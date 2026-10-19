<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->json('options')->nullable()->after('name');
        });

        foreach (DB::table('modifiers')->orderBy('id')->get() as $modifier) {
            DB::table('modifiers')->where('id', $modifier->id)->update([
                'options' => json_encode([$modifier->name]),
            ]);
        }

        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('name');
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
