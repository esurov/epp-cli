<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends Command
{
    private const GITHUB_API_URL = 'https://api.github.com/repos/esurov/epp-cli/releases/latest';

    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setDescription('Update epp-cli to the latest version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentVersion = $this->getApplication()->getVersion();

        if ($currentVersion === 'dev' || $currentVersion === '@'.'git_tag@') {
            $output->writeln('<error>Cannot self-update a development version. Please install a release build.</error>');

            return Command::FAILURE;
        }

        $output->writeln("Current version: <info>{$currentVersion}</info>");
        $output->writeln('Checking for updates...');

        $release = $this->fetchLatestRelease();
        if ($release === null) {
            $output->writeln('<error>Failed to fetch latest release from GitHub.</error>');

            return Command::FAILURE;
        }

        $latestVersion = $release['tag_name'];

        if (version_compare(ltrim($currentVersion, 'v'), ltrim($latestVersion, 'v'), '>=')) {
            $output->writeln("<info>You are already running the latest version ({$currentVersion}).</info>");

            return Command::SUCCESS;
        }

        $output->writeln("New version available: <info>{$latestVersion}</info>");

        $assetName = $this->resolveAssetName();
        $downloadUrl = $this->findAssetUrl($release, $assetName);

        if ($downloadUrl === null) {
            $output->writeln("<error>Could not find asset '{$assetName}' in the latest release.</error>");

            return Command::FAILURE;
        }

        $output->writeln("Downloading <info>{$assetName}</info>...");

        $currentBinary = $this->resolveCurrentBinary();
        if ($currentBinary === null) {
            $output->writeln('<error>Could not determine the path of the running binary.</error>');

            return Command::FAILURE;
        }

        $tempFile = $currentBinary.'.tmp';
        $backupFile = $currentBinary.'.bak';

        if (! $this->downloadFile($downloadUrl, $tempFile)) {
            $output->writeln('<error>Failed to download the update.</error>');
            @unlink($tempFile);

            return Command::FAILURE;
        }

        chmod($tempFile, fileperms($currentBinary));

        // Verify the downloaded binary works
        $versionCheck = shell_exec(escapeshellarg($tempFile).' --version 2>&1');
        if ($versionCheck === null || ! str_contains($versionCheck, $latestVersion)) {
            $output->writeln('<error>Downloaded binary verification failed. Aborting update.</error>');
            @unlink($tempFile);

            return Command::FAILURE;
        }

        // Backup current binary and replace
        @copy($currentBinary, $backupFile);

        if (! @rename($tempFile, $currentBinary)) {
            $output->writeln('<error>Failed to replace the current binary. Restoring backup...</error>');
            @rename($backupFile, $currentBinary);
            @unlink($tempFile);

            return Command::FAILURE;
        }

        @unlink($backupFile);

        $output->writeln("<info>Successfully updated to {$latestVersion}!</info>");

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchLatestRelease(): ?array
    {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: epp-cli\r\nAccept: application/vnd.github+json\r\n",
                'timeout' => 15,
            ],
        ]);

        $response = @file_get_contents(self::GITHUB_API_URL, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (! is_array($data) || ! isset($data['tag_name'])) {
            return null;
        }

        return $data;
    }

    private function resolveAssetName(): string
    {
        $pharPath = \Phar::running(false);
        if ($pharPath !== '') {
            return 'epp-cli.phar';
        }

        $os = match (php_uname('s')) {
            'Darwin' => 'macos',
            default => 'linux',
        };

        $arch = match (php_uname('m')) {
            'arm64', 'aarch64' => 'aarch64',
            default => 'x86_64',
        };

        return "epp-cli-{$os}-{$arch}";
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function findAssetUrl(array $release, string $assetName): ?string
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if ($asset['name'] === $assetName) {
                return $asset['browser_download_url'];
            }
        }

        return null;
    }

    private function resolveCurrentBinary(): ?string
    {
        $pharPath = \Phar::running(false);
        if ($pharPath !== '') {
            return $pharPath;
        }

        $argv0 = $_SERVER['argv'][0] ?? null;
        if ($argv0 === null) {
            return null;
        }

        $resolved = realpath($argv0);

        return $resolved ?: null;
    }

    private function downloadFile(string $url, string $destination): bool
    {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: epp-cli\r\nAccept: application/octet-stream\r\n",
                'timeout' => 60,
                'follow_location' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            return false;
        }

        return file_put_contents($destination, $content) !== false;
    }
}
