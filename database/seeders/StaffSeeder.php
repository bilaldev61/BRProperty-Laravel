<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One demo account per role, so the access rules can be tried from each side.
 *
 * These share a known password, which is fine on a demo site and never on a
 * real one, so the whole seeder is skipped unless demo login is switched on.
 */
class StaffSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: Role}> email => [name, role] */
    private const ACCOUNTS = [
        'manager@example.com' => ['Layla Haddad', Role::Manager],
        'agent@example.com' => ['Omar Rahman', Role::Agent],
        'editor@example.com' => ['Nadia Karim', Role::Editor],
    ];

    public function run(): void
    {
        if (! config('agent.demo.enabled')) {
            $this->command?->warn('Demo login is off (DEMO_LOGIN), so no demo staff were created.');

            return;
        }

        foreach (self::ACCOUNTS as $email => [$name, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make(config('agent.demo.password')), 'role' => $role],
            );

            $this->command?->line("  {$role->label()}: {$email}");
        }
    }
}
