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
            $table->boolean('shop_promo_visible')->default(false);
            $table->string('shop_promo_text', 200)->nullable();
            $table->string('shop_promo_background', 7)->default('#053b63');
            $table->string('shop_promo_color', 7)->default('#ffffff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'shop_promo_visible',
                'shop_promo_text',
                'shop_promo_background',
                'shop_promo_color',
            ]);
        });
    }
};
