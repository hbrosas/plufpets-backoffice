<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_filings', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable();
            $table->foreignId('deducted_payroll_id')->nullable()->constrained('employee_payrolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_filings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deducted_payroll_id');
            $table->dropColumn('amount');
        });
    }
};
