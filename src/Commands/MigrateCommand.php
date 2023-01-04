<?php

namespace Legodion\Lucid\Commands;

use Doctrine\DBAL\Schema\Comparator;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

class MigrateCommand extends Command
{
    use ConfirmableTrait;

    private $show_logs = false;

    private $models = [];

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

    public function migrateModels($show_logs = true)
    {

        $this->show_logs = $show_logs;

        $path = is_dir(app_path('Models')) ? app_path('Models') : app_path();
        $namespace = app()->getNamespace();

        $paths = array();

        array_push($paths, ['namespace' => $namespace . 'Models', 'file' => $path]);

        $modules_path = realpath(base_path()) . DIRECTORY_SEPARATOR . 'Modules';

        if (is_dir($modules_path)) {

            $dir = new \DirectoryIterator($modules_path);

            $this->logOutput("Paths Discovered", 'title');

            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot() && $fileinfo->isDir()) {
                    $module_name = $fileinfo->getFilename();
                    $module_path = $modules_path . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'Entities';
                    $this->logOutput($module_path);
                    array_push($paths, ['namespace' => 'Modules\\' . $module_name . '\\Entities', 'file' => $module_path]);
                }
            }
        }

        $this->logOutput("Model Classes Discovered", 'title');

        foreach ($paths as $key => $path) {

            foreach ((new Finder)->in($path['file'])->files() as $model) {

                $real_path_arr = array_reverse(explode(DIRECTORY_SEPARATOR, $model->getRealPath()));

                $model = $path['namespace'] . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    '\\' . $real_path_arr[0]
                );

                if (is_subclass_of($model, Model::class) && method_exists($model, 'migration')) {

                    $object = app($model);
                    $table_name = $object->getTable();

                    $this->logOutput("$table_name");

                    $this->models[$table_name] = [
                        'object' => $object,
                        'table' => $table_name,
                        'dependencies' => $object->migrationDependancy ?? [],
                        'order' => $object->migrationOrder ?? 0,
                        'processed' => false,
                    ];

                }
            }
        }

        $this->updateOrder($this->models);

        $this->logOutput("Process Tables", 'title');

        foreach (collect($this->models)->sortBy('order') as $model) {
            $this->migrateModel($model['object']);
        }exit;
    }

    protected function migrateModel(Model $model)
    {
        $this->logOutput(get_class($model));

        $modelTable = $model->getTable();
        $tempTable = 'table_' . $modelTable;

        Schema::dropIfExists($tempTable);

        Schema::create($tempTable, function (Blueprint $table) use ($model) {
            $model->migration($table);

            $table->boolean('is_modified')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        if (Schema::hasTable($modelTable)) {

            $manager = $model->getConnection()->getDoctrineSchemaManager();
            $manager->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

            $diff = (new Comparator)->diffTable($manager->listTableDetails($modelTable), $manager->listTableDetails($tempTable));

            if ($diff) {
                $manager->alterTable($diff);

                $this->logOutput(' -- Table ' . $modelTable . ' updated.');
            } else {
                $this->logOutput(' -- Table ' . $modelTable . ' is current.');
            }

            Schema::drop($tempTable);
        } else {
            Schema::rename($tempTable, $modelTable);

            $this->logOutput(' -- Table ' . $modelTable . ' created.');
        }

        if (method_exists($model, 'post_migration')) {

            try {
                Schema::table($modelTable, function (Blueprint $table) use ($model) {
                    $model->post_migration($table);
                });
                $this->logOutput(' -- Post Migration Successful.');
            } catch (\Throwable$th) {
                throw $th;
                $this->logOutput(' -- Post Migration Failed.');
            }
        }
    }

    private function updateOrder()
    {
        foreach ($this->models as $table_name => $model) {
            $this->processDependencies($table_name);
        }
    }

    private function processDependencies($table_name, $call_count = 0)
    {
        $orders = [];

        if ($call_count > 20) {
            $this->logOutput('Error processing dependencies. To many calls ' . $table_name . '.');
            return;
        }

        try {
            if (!empty($this->models[$table_name]['dependencies']) && !$this->models[$table_name]['processed']) {

                foreach ($this->models[$table_name]['dependencies'] as $dependency) {
                    $this->processDependencies($dependency, $call_count + 1);
                    array_push($orders, $this->models[$dependency]['order']);
                }

                if (!empty($orders)) {

                    sort($orders);

                    $order = (int) array_pop($orders) + 1;

                    if ($order) {
                        $this->models[$table_name]['order'] = $order;
                    }
                }
            }
        } catch (\Throwable$th) {
            //throw $th;
            $this->logOutput('Error with table ' . $table_name);
        }

        $this->models[$table_name]['processed'] = true;
    }

    private function logOutput($message, $type = 'info')
    {
        if ($this->show_logs) {

            if ($type == 'title') {
                print_r("\n");
                print_r("\e[42m " . $message . " \e[0m \n");
                print_r("\033[32mxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\033[0m\n");
                print_r("\n");

            }

            print($message . "\n");
        }
    }
}
