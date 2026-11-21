<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class PromoteUser extends Command
{
    protected $signature = 'user:promote {email : Email of an already registered user} {role=super_admin : user|venue_partner|safety_admin|super_admin}';

    protected $description = 'Give an existing user a role (use this to create your first admin in production)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email. Register through the website first.');

            return self::FAILURE;
        }

        $role = Role::findOrCreate($this->argument('role'));
        $user->syncRoles([$role->name]);
        $this->info("{$user->email} is now {$role->name}.");

        return self::SUCCESS;
    }
}
