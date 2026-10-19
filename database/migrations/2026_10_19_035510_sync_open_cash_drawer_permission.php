<?php

use App\Models\Permission;
use App\Support\AccessSetup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AccessSetup::install();
    }

    public function down(): void
    {
        Permission::query()->where('key', 'pos.open-drawer')->delete();
    }
};
