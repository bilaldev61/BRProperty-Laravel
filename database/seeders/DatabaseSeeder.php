<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // One setting behind every seeded password, so the login page's demo box
        // and these accounts can never disagree. DEMO_LOGIN_PASSWORD overrides it.
        $password = Hash::make(config('agent.demo.password'));

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => config('agent.name'), 'password' => $password, 'role' => Role::Owner],
        );

        $this->call([
            StaffSeeder::class,
            PropertySeeder::class,
            TestimonialSeeder::class,
            EnquirySeeder::class,
        ]);
    }
}
