<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role as the «Роли и права» screen shows it: a name people read, what it
 * is for, and its permissions. The built-in roles (RoleName) come with the
 * installation; the administrator changes them and builds others. The
 * administrator's own role can't be changed or removed — it always has every
 * right, so nobody locks the system out of itself.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $label
 * @property string|null $description
 */
class Role extends SpatieRole
{
    /**
     * Every role in the order people read them: the built-in ones as the
     * installation lists them, then the administrator's own by name.
     *
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        $builtIn = array_map(fn (RoleName $role): string => $role->value, RoleName::cases());

        return self::query()
            ->where('guard_name', 'web')
            ->get()
            ->sortBy(function (self $role) use ($builtIn): string {
                $position = array_search($role->name, $builtIn, true);

                return $position === false
                    ? '1 '.mb_strtolower($role->displayName())
                    : '0 '.$position;
            })
            ->values();
    }

    public function displayName(): string
    {
        return $this->label ?: (RoleName::tryFrom($this->name)?->label() ?? $this->name);
    }

    public function isAdministrator(): bool
    {
        return $this->name === RoleName::Admin->value;
    }
}
