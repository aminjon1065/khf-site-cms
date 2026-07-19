<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy that maps abilities to `module.action` permissions and applies
 * regional scoping. Superadmins are handled by a Gate::before hook.
 */
abstract class ModulePolicy
{
    abstract protected function module(): Module;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('view'));
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission('view')) && $this->inScope($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission('create'));
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission('edit')) && $this->inScope($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission('delete')) && $this->inScope($user, $model);
    }

    public function publish(User $user, Model $model): bool
    {
        return $user->can($this->permission('publish')) && $this->inScope($user, $model);
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can($this->permission('approve')) && $this->inScope($user, $model);
    }

    protected function permission(string $action): string
    {
        return $this->module()->value.'.'.$action;
    }

    /**
     * Regional editors are limited to content within their own region.
     * Models without a region are always in scope; override to restrict.
     */
    protected function inScope(User $user, Model $model): bool
    {
        if (! $user->hasRole('regional_editor') || $user->region_id === null) {
            return true;
        }

        return $this->belongsToUserRegion($user, $model);
    }

    protected function belongsToUserRegion(User $user, Model $model): bool
    {
        return true;
    }
}
