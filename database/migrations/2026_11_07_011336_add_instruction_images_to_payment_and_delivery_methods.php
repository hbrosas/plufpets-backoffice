<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->json('instruction_images')->nullable();
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->json('instruction_images')->nullable();
        });

        Schema::table('payment_method_instructions', function (Blueprint $table) {
            $table->json('images')->nullable();
        });

        Schema::table('delivery_method_instructions', function (Blueprint $table) {
            $table->json('images')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('instruction_images');
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropColumn('instruction_images');
        });

        Schema::table('payment_method_instructions', function (Blueprint $table) {
            $table->dropColumn('images');
        });

        Schema::table('delivery_method_instructions', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
