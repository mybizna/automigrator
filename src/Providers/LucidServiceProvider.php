<?php

namespace Mybizna\Lucid\Providers;

use Illuminate\Support\ServiceProvider;
use Mybizna\Lucid\Commands\MigrateCommand;
use Mybizna\Lucid\Commands\ModelCommand;
use Mybizna\Lucid\Commands\ResourceCommand;

class LucidServiceProvider extends ServiceProvider
{
    public function boot()
    {

        $this->initializeConfig();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCommand::class,
                ModelCommand::class,
                ResourceCommand::class,
            ]);
        }
    }

    private function initializeConfig()
    {
        $logging_config = $this->app['config']->get('logging', []);
        $logging_config['channels']['migration'] = [
            'driver' => 'single',
            'path' => storage_path('logs/migration.log'),
        ];
        $this->app['config']->set('logging', $logging_config);

    }
}
