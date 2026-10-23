<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetRole extends Command
{
    protected $signature = 'mqmesh:set-role {email} {role=admin : user, moderator or admin}';

    protected $description = "Change a user's role";

    public function handle(): int
    {
        $role = $this->argument('role');

        if (! in_array($role, [User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN], true)) {
            $this->error('Role must be user, moderator or admin.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email. Register on the site first.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => $role])->save();
        $this->info("{$user->name} is now {$role}.");

        return self::SUCCESS;
    }
}
