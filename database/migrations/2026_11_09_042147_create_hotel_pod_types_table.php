<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_pod_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $labels = [
            'cat' => 'Cat',
            'small_dog' => 'Small dog',
            'medium_dog' => 'Medium dog',
            'large_dog' => 'Large dog',
            'suite' => 'Suite',
        ];

        foreach (array_values($labels) as $index => $name) {
            DB::table('hotel_pod_types')->insert([
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('hotel_stays', function (Blueprint $table) {
            $table->foreignId('hotel_pod_type_id')
                ->nullable()
                ->constrained('hotel_pod_types')
                ->restrictOnDelete();
        });

        $ids = DB::table('hotel_pod_types')->pluck('id', 'name');

        foreach ($labels as $value => $name) {
            DB::table('hotel_stays')->where('pod_type', $value)->update([
                'hotel_pod_type_id' => $ids[$name],
            ]);
        }

        $unknown = DB::table('hotel_stays')
            ->whereNull('hotel_pod_type_id')
            ->whereNotNull('pod_type')
            ->distinct()
            ->pluck('pod_type');

        foreach ($unknown as $value) {
            $name = Str::of((string) $value)->replace('_', ' ')->headline()->toString();
            $id = DB::table('hotel_pod_types')->where('name', $name)->value('id');

            if ($id === null) {
                $id = DB::table('hotel_pod_types')->insertGetId([
                    'name' => $name,
                    'sort_order' => (int) DB::table('hotel_pod_types')->max('sort_order') + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('hotel_stays')->where('pod_type', $value)->whereNull('hotel_pod_type_id')->update([
                'hotel_pod_type_id' => $id,
            ]);
        }

        Schema::table('hotel_stays', function (Blueprint $table) {
            $table->dropColumn('pod_type');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_stays', function (Blueprint $table) {
            $table->string('pod_type')->nullable();
        });

        $values = [
            'Cat' => 'cat',
            'Small dog' => 'small_dog',
            'Medium dog' => 'medium_dog',
            'Large dog' => 'large_dog',
            'Suite' => 'suite',
        ];

        foreach (DB::table('hotel_pod_types')->pluck('name', 'id') as $id => $name) {
            DB::table('hotel_stays')->where('hotel_pod_type_id', $id)->update([
                'pod_type' => $values[$name] ?? Str::snake((string) $name),
            ]);
        }

        Schema::table('hotel_stays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_pod_type_id');
        });

        Schema::dropIfExists('hotel_pod_types');
    }
};
