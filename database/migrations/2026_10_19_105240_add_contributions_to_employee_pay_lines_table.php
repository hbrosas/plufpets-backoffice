<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_pay_lines', function (Blueprint $table) {
            $table->decimal('employee_contribution', 12, 2)->nullable();
            $table->decimal('employer_contribution', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        DB::table('employee_pay_lines')
            ->where('kind', 'government')
            ->whereNull('employee_contribution')
            ->update([
                'employee_contribution' => DB::raw('amount'),
                'employer_contribution' => 0,
                'amount' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('employee_pay_lines')
            ->where('kind', 'government')
            ->whereNull('amount')
            ->update([
                'amount' => DB::raw('coalesce(employee_contribution, 0)'),
            ]);

        DB::table('employee_pay_lines')->whereNull('amount')->update(['amount' => 0]);

        Schema::table('employee_pay_lines', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable(false)->change();
            $table->dropColumn(['employee_contribution', 'employer_contribution']);
        });
    }
};
