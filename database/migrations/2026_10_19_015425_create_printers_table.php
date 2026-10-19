<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role');
            $table->string('connection_type');
            $table->unsignedSmallInteger('paper_width_mm');
            $table->unsignedSmallInteger('paper_height_mm')->nullable();
            $table->unsignedInteger('baud_rate')->default(9600);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usb_vendor_id')->nullable();
            $table->unsignedInteger('usb_product_id')->nullable();
            $table->string('usb_serial_number')->nullable();
            $table->string('device_name')->nullable();
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
