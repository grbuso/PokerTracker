<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('admin.email');

        if (blank($email)) {
            $this->command?->warn('ADMIN_EMAIL não configurado. Nenhum administrador foi criado.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $user->exists ? $user->name : config('admin.name');
        $user->email_verified_at ??= now();
        $user->is_admin = true;

        if (! $user->exists) {
            $password = config('admin.password');

            if (blank($password)) {
                $this->command?->warn('Defina ADMIN_PASSWORD para criar a conta de administrador.');

                return;
            }

            $user->password = $password;
        }

        $user->save();
    }
}
