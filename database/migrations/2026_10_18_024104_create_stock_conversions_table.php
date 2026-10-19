<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_catalog_item_variant_id')->constrained('catalog_item_variants')->restrictOnDelete();
            $table->foreignId('to_catalog_item_variant_id')->constrained('catalog_item_variants')->restrictOnDelete();
            $table->unsignedInteger('from_quantity');
            $table->unsignedInteger('to_quantity');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_conversions');
    }
};
