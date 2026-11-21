<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Safe seeder for the live database: roles + real map data only.
 * It does NOT create the test accounts (admin@test.com / password) that
 * RoleSeeder creates for local development.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['user', 'venue_partner', 'safety_admin', 'super_admin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->call(SafeHavenSeeder::class);
    }
}
