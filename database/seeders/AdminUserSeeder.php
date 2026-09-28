<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_SEED_EMAIL', 'admin@bridgewaydigital.test');
        $name = (string) env('ADMIN_SEED_NAME', 'Bridgeway Digital Administrator');
        $password = (string) env('ADMIN_SEED_PASSWORD', '');
        $resetPassword = filter_var(env('ADMIN_SEED_RESET_PASSWORD', false), FILTER_VALIDATE_BOOL);

        if ($password === '' && app()->environment(['local', 'testing'])) {
            $password = 'BridgewayLocalAdmin!2026';
        }

        if ($password === '') {
            throw new \RuntimeException('ADMIN_SEED_PASSWORD must be set before creating or resetting the root admin user.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'display_name' => $user->display_name ?: $name,
            'status' => true,
            'is_root' => true,
        ]);
        $user->email_verified_at = $user->email_verified_at ?: now();

        if (! $user->exists || $resetPassword || blank($user->password)) {
            $user->password = Hash::make($password);
        }

        $user->save();
    }
}
