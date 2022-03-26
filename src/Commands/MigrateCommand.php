<?php

namespace Legodion\Lucid\Commands;

use Doctrine\DBAL\Schema\Comparator;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class MigrateCommand extends Command
{
    use ConfirmableTrait;
    
    protected $signature = 'lucid:migrate {--f|--fresh} {--s|--seed} {--force}';

    public function handle()
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->call($this->option('fresh') ? 'migrate:fresh' : 'migrate', ['--force' => true]);

        $this->migrateModels();

        if ($this->option('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        return 0;
    }

    protected function migrateModels()
    {
        $path = is_dir(app_path('Models')) ? app_path('Models') : app_path();
        $namespace = app()->getNamespace();
        $models = collect();

        foreach ((new Finder)->in($path)->files() as $model) {
            $model = $namespace . str_replace(
                ['/', '.php'], ['\\', ''], Str::after($model->getRealPath(), realpath(app_path()) . DIRECTORY_SEPARATOR)
            );

            if (is_subclass_of($model, Model::class) && method_exists($model, 'migration')) {
                $models->push([
                    'object' => $object = app($model),
                    'order' => $object->migrationOrder ?? 0,
                ]);
            }
        }

        foreach ($models->sortBy('order') as $model) {
            $this->migrateModel($model['object']);
        }
    }

    protected function migrateModel(Model $model)
    {
        $modelTable = $model->getTable();
        $tempTable = 'table_' . $modelTable;

        Schema::dropIfExists($tempTable);

        Schema::create($tempTable, function (Blueprint $table) use ($model) {
            $model->migration($table);
        });

        if (Schema::hasTable($modelTable)) {
            $manager = $model->getConnection()->getDoctrineSchemaManager();
            $diff = (new Comparator)->diffTable($manager->listTableDetails($modelTable), $manager->listTableDetails($tempTable));

            if ($diff) {
                $manager->alterTable($diff);

                $this->line('<info>Table updated:</info> ' . $modelTable);
            }

            Schema::drop($tempTable);
        } else {
            Schema::rename($tempTable, $modelTable);

            $this->line('<info>Table created:</info> ' . $modelTable);
        }
    }
}
