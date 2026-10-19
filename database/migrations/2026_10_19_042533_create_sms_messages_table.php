<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->text('body');
            $table->string('employee_number');
            $table->string('employee_name');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['sent_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
