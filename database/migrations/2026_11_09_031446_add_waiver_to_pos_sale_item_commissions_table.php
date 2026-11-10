<?php

use App\Models\Permission;
use App\Support\AccessSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_item_commissions', function (Blueprint $table) {
            $table->timestamp('waived_at')->nullable()->after('amount');
            $table->string('waived_by_employee_number')->nullable()->after('waived_at');
            $table->string('waived_by_name')->nullable()->after('waived_by_employee_number');
        });

        AccessSetup::install();
    }

    public function down(): void
    {
        Permission::query()->where('key', 'sales.waive-commission')->delete();

        Schema::table('pos_sale_item_commissions', function (Blueprint $table) {
            $table->dropColumn(['waived_at', 'waived_by_employee_number', 'waived_by_name']);
        });
    }
};
