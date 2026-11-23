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
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('testing_mode')->default(false);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('for_test_use')->default(false);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('for_test_use')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('testing_mode');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('for_test_use');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('for_test_use');
        });
    }
};
