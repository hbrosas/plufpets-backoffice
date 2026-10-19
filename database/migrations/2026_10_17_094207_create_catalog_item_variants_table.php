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
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->string('image_path')->nullable()->after('brand_id');
        });

        Schema::create('catalog_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 64)->nullable()->unique();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['catalog_item_id', 'name']);
        });

        $now = now();

        foreach (DB::table('catalog_items')->orderBy('id')->get() as $item) {
            DB::table('catalog_item_variants')->insert([
                'catalog_item_id' => $item->id,
                'name' => 'Default',
                'sku' => $item->sku,
                'price' => $item->price,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'price', 'stock_quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('sku', 64)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
        });

        foreach (DB::table('catalog_item_variants')->orderBy('id')->get() as $variant) {
            DB::table('catalog_items')->where('id', $variant->catalog_item_id)->update([
                'sku' => $variant->sku,
                'price' => $variant->price,
            ]);
        }

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->unique('sku');
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('image_path');
        });

        Schema::dropIfExists('catalog_item_variants');
    }
};
