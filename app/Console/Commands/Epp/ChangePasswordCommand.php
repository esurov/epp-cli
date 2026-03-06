<?php

namespace App\Console\Commands\Epp;

use App\EppCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\password;

class ChangePasswordCommand extends EppCommand
{
    protected function configure(): void
    {
        $this
            ->setName('password:change')
            ->setDescription('Change the EPP login password')
            ->addOption('newpassword', null, InputOption::VALUE_REQUIRED, 'New EPP password (8-16 chars)')
            ->addOption('cltrid', null, InputOption::VALUE_REQUIRED, 'Client transaction ID (4-64 chars)')
            ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'Directory for EPP log files');
    }

    protected function handle(): int
    {
        $newPassword = $this->askIfMissing('newpassword', fn () => password('Enter the new password (8-16 chars):', required: true));

        $this->printCliEquivalent();

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
