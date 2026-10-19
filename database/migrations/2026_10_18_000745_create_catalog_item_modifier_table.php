<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalog_item_modifier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['catalog_item_id', 'modifier_id']);
        });

        if (Schema::hasTable('catalog_item_modifiers')) {
            $now = now();

            foreach (DB::table('catalog_item_modifiers')->orderBy('id')->get() as $row) {
                $modifierId = DB::table('modifiers')->where('name', $row->name)->value('id');

                if ($modifierId === null) {
                    $modifierId = DB::table('modifiers')->insertGetId([
                        'name' => $row->name,
                        'price' => $row->price,
                        'is_active' => $row->is_active,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $exists = DB::table('catalog_item_modifier')
                    ->where('catalog_item_id', $row->catalog_item_id)
                    ->where('modifier_id', $modifierId)
                    ->exists();

                if (! $exists) {
                    DB::table('catalog_item_modifier')->insert([
                        'catalog_item_id' => $row->catalog_item_id,
                        'modifier_id' => $modifierId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            Schema::drop('catalog_item_modifiers');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('catalog_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::dropIfExists('catalog_item_modifier');
    }
};
