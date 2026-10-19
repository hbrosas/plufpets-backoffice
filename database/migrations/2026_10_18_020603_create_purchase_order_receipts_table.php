<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_receipt_number')->nullable();
            $table->string('sales_invoice_number')->nullable();
            $table->string('received_by_employee_number', 32);
            $table->string('received_by_name');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['purchase_order_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipts');
    }
};
