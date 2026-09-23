<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The first administrator of a fresh instance. In production `db:seed` no
 * longer creates the demo accounts (they share a known password), so the
 * account that then creates everybody else is made here, with a password
 * only the operator knows. The role must already exist: run the seeders
 * (roles and permissions) first.
 */
#[Signature('cms:create-admin {--email= : Электронная почта} {--name= : Имя и фамилия}')]
#[Description('Создать суперадминистратора CMS (первый вход в новой установке)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        if (! Role::query()->where('name', RoleName::Superadmin->value)->exists()) {
            $this->components->error('Роли ещё не созданы. Сначала выполните php artisan db:seed --force.');

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?: text(
            label: 'Электронная почта',
            required: true,
            validate: fn (string $value): ?string => $this->emailError($value),
        ));

        if (($error = $this->emailError($email)) !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: text(label: 'Имя и фамилия', required: true));

        $plainPassword = password(
            label: 'Пароль',
            required: true,
            validate: fn (string $value): ?string => $this->passwordError($value),
        );
        $confirmation = password(label: 'Повторите пароль', required: true);

        if ($plainPassword !== $confirmation) {
            $this->components->error('Пароли не совпадают — учётная запись не создана.');

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'is_active' => true,
            'interface_locale' => 'ru',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(RoleName::Superadmin->value);

        activity('users')
            ->performedOn($user)
            ->event('created')
            ->log('Суперадминистратор создан из консоли (cms:create-admin)');

        $this->components->info("Суперадминистратор {$email} создан. При первом входе система попросит настроить двухфакторную аутентификацию, если она обязательна.");

        return self::SUCCESS;
    }

    private function emailError(string $email): ?string
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'max:255', 'unique:users,email']],
        );

        return $validator->fails() ? $validator->errors()->first('email') : null;
    }

    private function passwordError(string $password): ?string
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::default()]],
        );

        return $validator->fails() ? $validator->errors()->first('password') : null;
    }
}
