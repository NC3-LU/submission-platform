<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetTwoFactor extends Command
{
    protected $signature = 'app:reset-2fa';

    protected $description = 'Reset two-factor authentication for all users';

    public function handle()
    {
        $count = User::whereNotNull('two_factor_secret')->count();

        if ($count === 0) {
            $this->info('No users have 2FA enabled.');

            return;
        }

        $this->warn("{$count} user(s) have 2FA enabled.");

        if (! $this->confirm('Reset 2FA for all users?')) {
            $this->info('Aborted.');

            return;
        }

        User::whereNotNull('two_factor_secret')->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->info("2FA reset for {$count} user(s).");
    }
}
