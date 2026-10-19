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
        Schema::table('catalog_item_variants', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('barcode');
        });

        foreach (DB::table('catalog_items')->whereNotNull('image_path')->orderBy('id')->get() as $item) {
            $variantId = DB::table('catalog_item_variants')
                ->where('catalog_item_id', $item->id)
                ->orderBy('id')
                ->value('id');

            if ($variantId === null) {
                continue;
            }

            DB::table('catalog_item_variants')
                ->where('id', $variantId)
                ->update(['image_path' => $item->image_path]);
        }

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('brand_id');
        });

        foreach (DB::table('catalog_item_variants')->whereNotNull('image_path')->orderBy('id')->get() as $variant) {
            $hasItemImage = DB::table('catalog_items')
                ->where('id', $variant->catalog_item_id)
                ->whereNotNull('image_path')
                ->exists();

            if ($hasItemImage) {
                continue;
            }

            DB::table('catalog_items')
                ->where('id', $variant->catalog_item_id)
                ->update(['image_path' => $variant->image_path]);
        }

        Schema::table('catalog_item_variants', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
