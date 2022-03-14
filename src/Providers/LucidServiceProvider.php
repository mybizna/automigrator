<?php

namespace Legodion\Lucid\Providers;

use Legodion\Lucid\Commands\MigrateCommand;
use Legodion\Lucid\Commands\ModelCommand;
use Legodion\Lucid\Commands\ResourceCommand;
use Illuminate\Support\ServiceProvider;

class LucidServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCommand::class,
                ModelCommand::class,
                ResourceCommand::class,
            ]);
        }
    }
}
