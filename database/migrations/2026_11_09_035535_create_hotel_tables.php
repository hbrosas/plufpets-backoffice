<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('pos_shift_id')->constrained();
            $table->string('client_name');
            $table->string('contact_number', 30);
            $table->text('address');
            $table->dateTime('checked_in_at');
            $table->date('checkout_on');
            $table->dateTime('checked_out_at')->nullable();
            $table->string('pod_type');
            $table->string('checked_in_by_employee_number');
            $table->string('checked_in_by_name');
            $table->string('checked_out_by_employee_number')->nullable();
            $table->string('checked_out_by_name')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_stay_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_stay_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('breed');
            $table->string('age', 50);
            $table->string('gender');
            $table->boolean('has_vaccine_booklet')->default(false);
            $table->json('vaccines');
            $table->text('feeding_instructions')->nullable();
            $table->text('medication_instructions')->nullable();
            $table->text('special_instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_stay_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_stay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_shift_id')->constrained();
            $table->foreignId('pos_cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_name');
            $table->boolean('is_cash')->default(false);
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_tendered', 12, 2)->nullable();
            $table->decimal('change_due', 12, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->string('recorded_by_employee_number');
            $table->string('recorded_by_name');
            $table->dateTime('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_stay_payments');
        Schema::dropIfExists('hotel_stay_pets');
        Schema::dropIfExists('hotel_stays');
    }
};
