<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table) {
            $table->dropUnique(['scheduled_on', 'starts_at', 'ends_at']);
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('date')->after('branch_id');
            $table->string('frequency')->nullable()->after('kind');
            $table->json('weekdays')->nullable()->after('frequency');
            $table->date('scheduled_on')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['kind', 'frequency', 'weekdays']);
            $table->date('scheduled_on')->nullable(false)->change();
            $table->unique(['scheduled_on', 'starts_at', 'ends_at']);
        });
    }
};
