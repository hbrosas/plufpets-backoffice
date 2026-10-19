<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
        });

        $firstBranchId = DB::table('branches')->orderBy('id')->value('id');

        if ($firstBranchId !== null) {
            DB::table('printers')->whereNull('branch_id')->update(['branch_id' => $firstBranchId]);
        }
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
