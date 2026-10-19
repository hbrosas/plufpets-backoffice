<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('sold_at');
            $table->string('refunded_by_employee_number')->nullable()->after('refunded_at');
            $table->string('refunded_by_name')->nullable()->after('refunded_by_employee_number');
            $table->string('refund_reason')->nullable()->after('refunded_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn([
                'refunded_at',
                'refunded_by_employee_number',
                'refunded_by_name',
                'refund_reason',
            ]);
        });
    }
};
