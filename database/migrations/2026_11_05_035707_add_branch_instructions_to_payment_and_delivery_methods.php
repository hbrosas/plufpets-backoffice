<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('instructions_for_all_branches')->default(true);
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->boolean('instructions_for_all_branches')->default(true);
        });

        Schema::create('payment_method_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['payment_method_id', 'branch_id']);
        });

        Schema::create('delivery_method_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['delivery_method_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_method_instructions');
        Schema::dropIfExists('payment_method_instructions');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('instructions_for_all_branches');
        });

        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropColumn('instructions_for_all_branches');
        });
    }
};
