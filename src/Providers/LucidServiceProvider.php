<?php

namespace Legodion\Lucid\Providers;

use Illuminate\Support\ServiceProvider;
use Legodion\Lucid\Commands\MigrateCommand;
use Legodion\Lucid\Commands\ModelCommand;
use Legodion\Lucid\Commands\ResourceCommand;

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
