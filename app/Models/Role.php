<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @param  array<int, string>  $permissionKeys
     */
    public function wouldRemoveLastStaffManagement(array $permissionKeys): bool
    {
        $currentlyGrants = $this->permissions()->where('key', 'staff.manage')->exists();
        $willGrant = in_array('staff.manage', $permissionKeys, true);

        if (! $currentlyGrants || $willGrant) {
            return false;
        }

        $assignedToActiveEmployee = $this->employees()->where('is_active', true)->exists();

        if (! $assignedToActiveEmployee) {
            return false;
        }

        return Employee::query()
            ->where('is_active', true)
            ->where('role_id', '!=', $this->id)
            ->whereHas('role.permissions', function ($query): void {
                $query->where('key', 'staff.manage');
            })
            ->doesntExist();
    }
}
