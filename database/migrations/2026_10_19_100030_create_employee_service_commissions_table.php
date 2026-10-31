<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_service_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('assigned_rate', 5, 2)->default(0);
            $table->decimal('assisted_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_service_commissions');
    }
};
