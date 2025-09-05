<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateCommand extends Command
{
    protected $signature = 'spike:update';

    protected $description = 'Update all resources after updating the Spike package.';

    public function handle()
    {
        if (file_exists(public_path('vendor/spike'))) {
            $this->info('Spike Assets previously published. Updating...');
            $this->call('vendor:publish', ['--tag' => 'spike-assets', '--force' => true]);
        }

        if (file_exists(resource_path('views/vendor/spike/components/layout.blade.php'))) {
            File::move(
                resource_path('views/vendor/spike/components/layout.blade.php'),
                resource_path('views/vendor/spike/components/layout-backup.blade.php')
            );
            $this->info('Spike Layout previously published. Updating...');
            $this->call('vendor:publish', ['--tag' => 'spike-layout', '--force' => true]);
        }

        if (file_exists(resource_path('lang/vendor/spike/en'))) {
            $this->info('Spike Translations previously published. Updating...');
            $this->call('vendor:publish', ['--tag' => 'spike-translations', '--force' => true]);
            $this->info('Make sure to double-check the translations! Some may have been overwritten, or require updating.');
        }

        $this->info('Publishing new migration files...');
        $this->call('vendor:publish', ['--tag' => 'spike-migrations', '--force' => true]);

        $this->info('### All done! ###');

        return Command::SUCCESS;
    }
}
