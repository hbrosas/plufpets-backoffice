<?php

use App\Support\AccessSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->nullable();
            $table->json('checkout')->nullable();
            $table->index('status');
        });

        AccessSetup::install();
    }

    public function down(): void
    {
        Schema::table('pos_open_orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['phone', 'status', 'discount', 'total', 'checkout']);
        });
    }
};
