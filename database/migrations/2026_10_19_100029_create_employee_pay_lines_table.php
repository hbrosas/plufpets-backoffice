<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_pay_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pay_lines');
    }
};
