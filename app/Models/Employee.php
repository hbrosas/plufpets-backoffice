<?php

namespace App\Models;

use App\Enums\EmployeeHomeScreen;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'date_of_birth', 'start_date', 'employee_number', 'pin', 'role_id', 'is_active', 'home_screen'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'pin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'start_date' => 'date',
            'employee_number' => 'string',
            'pin' => 'hashed',
            'is_active' => 'boolean',
            'home_screen' => EmployeeHomeScreen::class,
        ];
    }

    public function homeUrl(): string
    {
        $this->loadMissing('role.permissions');

        $permissions = $this->role?->permissions->pluck('key')->all() ?? [];
        $canUsePos = in_array('pos.access', $permissions, true);
        $canViewDashboard = in_array('dashboard.view', $permissions, true);

        if ($this->home_screen === EmployeeHomeScreen::Pos && $canUsePos) {
            return route('pos.launch');
        }

        if ($canViewDashboard || ! $canUsePos) {
            return route('dashboard');
        }

        return route('pos.launch');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $key): bool
    {
        return $this->role?->permissions()->where('key', $key)->exists() ?? false;
    }

    public function isOnlyActiveStaffManager(): bool
    {
        if (! $this->is_active || ! $this->hasPermission('staff.manage')) {
            return false;
        }

        return static::query()
            ->where('is_active', true)
            ->whereKeyNot($this->id)
            ->whereHas('role.permissions', function ($query): void {
                $query->where('key', 'staff.manage');
            })
            ->doesntExist();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithNextNumber(array $attributes): self
    {
        unset($attributes['employee_number'], $attributes['id']);

        $attempt = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($attributes): self {
                    $latest = (int) static::query()
                        ->lockForUpdate()
                        ->orderByDesc('employee_number')
                        ->value('employee_number');

                    return static::query()->create([
                        ...$attributes,
                        'employee_number' => max($latest + 1, 10001),
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                $attempt++;

                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
