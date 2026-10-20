<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteSystemAdministrator extends Command
{
    protected $signature = 'users:promote-administrator {email : Email address of an existing account}';

    protected $description = 'Assign the System Administrator role to an existing account.';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error('No account exists with that email address.');

            return self::FAILURE;
        }

        if ($user->role === 'System Administrator') {
            $this->components->info('This account is already a System Administrator.');

            return self::SUCCESS;
        }

        $user->role = 'System Administrator';
        $user->save();

        $this->components->info("{$user->email} is now a System Administrator.");

        return self::SUCCESS;
    }
}
