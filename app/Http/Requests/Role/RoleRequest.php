<?php

namespace App\Http\Requests\Role;

use App\Enums\RoleName;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A role built or changed on the «Роли и права» screen. Only the
 * administrator builds roles, and only from the rights that may be granted:
 * managing accounts and settings stays the administrator's.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(RoleName::Admin->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique(Role::class, 'label')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionMatrix::grantable())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Назовите роль.',
            'label.max' => 'Название — не длиннее 60 символов.',
            'label.unique' => 'Роль с таким названием уже есть.',
            'description.max' => 'Описание — не длиннее 255 символов.',
            'permissions.*.in' => 'Управлять пользователями и настройками может только администратор.',
        ];
    }

    /**
     * The rights picked, made consistent (PermissionMatrix::normalize).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        /** @var array<int, string> $picked */
        $picked = $this->array('permissions');

        return PermissionMatrix::normalize($picked);
    }
}
