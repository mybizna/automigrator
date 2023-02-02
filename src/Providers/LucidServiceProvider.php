<?php

namespace Mybizna\Automigrator\Providers;

use Illuminate\Support\ServiceProvider;
use Mybizna\Automigrator\Commands\MigrateCommand;
use Mybizna\Automigrator\Commands\ModelCommand;
use Mybizna\Automigrator\Commands\ResourceCommand;

class AutomigratorServiceProvider extends ServiceProvider
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
