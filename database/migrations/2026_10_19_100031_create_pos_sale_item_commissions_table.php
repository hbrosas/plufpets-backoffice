<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_item_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number');
            $table->string('employee_name');
            $table->string('role');
            $table->decimal('rate', 5, 2);
            $table->unsignedSmallInteger('pool_size');
            $table->decimal('commissionable_amount', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_item_commissions');
    }
};
