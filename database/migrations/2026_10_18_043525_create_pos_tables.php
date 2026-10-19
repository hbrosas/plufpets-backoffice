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
        Schema::create('pos_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('catalog_item_variants', function (Blueprint $table) {
            $table->foreignId('pos_category_id')->nullable()->constrained('pos_categories')->nullOnDelete();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false);
        });

        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('opened_by_employee_number');
            $table->string('opened_by_name');
            $table->timestamp('opened_at');
            $table->string('closed_by_employee_number')->nullable();
            $table->string('closed_by_name')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('starting_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->decimal('remit_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'opened_at']);
        });

        DB::statement('CREATE UNIQUE INDEX pos_shifts_one_open_per_branch ON pos_shifts (branch_id) WHERE closed_at IS NULL');

        Schema::create('pos_shift_denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $table->string('count_type');
            $table->decimal('denomination', 10, 2);
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['pos_shift_id', 'count_type']);
        });

        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->string('employee_number');
            $table->string('employee_name');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['pos_shift_id', 'type']);
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_name');
            $table->boolean('is_cash')->default(false);
            $table->foreignId('delivery_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_method_name')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_tendered', 12, 2)->nullable();
            $table->decimal('change_due', 12, 2)->default(0);
            $table->string('sold_by_employee_number');
            $table->string('sold_by_name');
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['pos_shift_id', 'sold_at']);
            $table->index(['branch_id', 'sold_at']);
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('catalog_item_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('pos_sale_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('tax_fee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sale_taxes');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_shift_denominations');
        Schema::dropIfExists('pos_shifts');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_cash');
        });

        Schema::table('catalog_item_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_category_id');
        });

        Schema::dropIfExists('pos_categories');
    }
};
