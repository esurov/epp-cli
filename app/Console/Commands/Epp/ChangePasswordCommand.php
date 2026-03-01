<?php

namespace App\Console\Commands\Epp;

use App\Concerns\InteractsWithEpp;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

class ChangePasswordCommand extends Command
{
    use InteractsWithEpp;

    protected $signature = 'epp:change-password
        {--newpassword= : New EPP password (8-16 chars)}
        {--cltrid= : Client transaction ID (4-64 chars)}
        {--logdir= : Directory for EPP log files}';

    protected $description = 'Change the EPP login password';

    public function handle(): int
    {
        $newPassword = $this->option('newpassword') ?? password('Enter the new password (8-16 chars):', required: true);

        if (! $this->isValidToken($newPassword, 8, 16)) {
            $this->error('--newpassword must be between 8 and 16 characters');

            return self::FAILURE;
        }

        return $this->executeEppOperation(function ($connection) {
            $this->line('SUCCESS: The EPP-Password has been changed');
        }, $newPassword);
    }

    private function isValidToken(string $value, int $min, int $max): bool
    {
        if (preg_match('/[\r\n\t]/', $value)) {
            return false;
        }

        if (preg_match('/^\s/', $value) || preg_match('/\s$/', $value) || preg_match('/\s\s/', $value)) {
            return false;
        }

        $length = strlen($value);

        return $length >= $min && $length <= $max;
    }
}
