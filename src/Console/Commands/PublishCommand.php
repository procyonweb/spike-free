<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Opcodes\Spike\SpikeServiceProvider;
use Spatie\Watcher\Watch;

class PublishCommand extends Command
{
    protected $signature = 'spike:publish {--watch}';

    protected $description = 'Publish Spike assets';

    public function handle(): void
    {
        $this->publishSpikeAssets();

        if ($this->option('watch')) {
            $this->watchForFileChanges();
        }
    }

    protected function publishSpikeAssets(bool $force = true): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'spike-assets',
            '--force' => $force,
        ]);
    }

    protected function watchForFileChanges(): void
    {
        if (! class_exists(Watch::class)) {
            $this->error('Please install the spatie/file-system-watcher package to use the --watch option.');
            $this->info('Learn more at https://github.com/spatie/file-system-watcher');

            return;
        }

        $this->info('Watching for file changes... (Press CTRL+C to stop)');

        Watch::path(SpikeServiceProvider::basePath('/resources/dist'))
            ->onAnyChange(function (string $type, string $path) {
                $this->publishSpikeAssets();
            })
            ->start();
    }
}
