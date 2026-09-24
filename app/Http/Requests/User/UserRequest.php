<?php

namespace App\Http\Requests\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via policies + guards.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $isCreate = $user === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [
                $isCreate ? 'required' : 'nullable',
                'confirmed',
                Password::default(),
            ],
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')->where('guard_name', 'web')],
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id'), 'required_if_accepted:limited_to_region'],
            'limited_to_region' => ['boolean'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'interface_locale' => ['nullable', Rule::in(['tg', 'ru'])],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя сотрудника.',
            'email.required' => 'Укажите e-mail.',
            'email.unique' => 'Пользователь с таким e-mail уже существует.',
            'password.required' => 'Задайте пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
            'role.required' => 'Выберите роль.',
            'role.exists' => 'Такой роли нет — выберите из списка.',
            'region_id.required_if_accepted' => 'Чтобы ограничить сотрудника регионом, выберите регион.',
        ];
    }
}
