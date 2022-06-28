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

    public function migrateModels()
    {
        $path = is_dir(app_path('Models')) ? app_path('Models') : app_path();
        $namespace = app()->getNamespace();

        $paths = array();

        array_push($paths, ['namespace' => $namespace  . 'Models', 'file' => $path]);

        $modules_path = realpath(base_path()) . DIRECTORY_SEPARATOR . 'Modules';

        if (is_dir($modules_path)) {

            $dir = new \DirectoryIterator($modules_path);

            print_r("\n");
            print_r("\e[42m Paths Discovered \e[0m \n");
            print_r("\033[32mxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\033[0m\n");
            print_r("\n");

            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot() && $fileinfo->isDir()) {
                    $module_name = $fileinfo->getFilename();
                    $module_path = $modules_path . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'Entities';
                    print_r($module_path . "\n");
                    array_push($paths, ['namespace' => 'Modules\\'  . $module_name . '\\Entities', 'file' => $module_path]);
                }
            }
        }

        print_r("\n");
        print_r("\e[42m Model Classes Discovered \e[0m \n");
        print_r("\033[32mxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\033[0m\n");
        print_r("\n");

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
                    print_r($object->getTable() . "\n");

                    $this->models[$object->getTable()] = [
                        'object' => $object,
                        'table' => $object->getTable(),
                        'dependencies' => $object->migrationDependancy ?? [],
                        'order' => $object->migrationOrder ?? 0,
                        'processed' =>  false,
                    ];
                }
            }
        }

        $this->updateOrder($this->models);


        print_r("\n");
        print_r("\e[42m Process Tables \e[0m \n");
        print_r("\033[32mxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\033[0m\n");
        print_r("\n");


        foreach (collect($this->models)->sortBy('order') as $model) {
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

                $this->line('<info>Table updated:</info> ' . $modelTable);
            } else {
                $this->line('Table is current: ' . $modelTable);
            }

            Schema::drop($tempTable);
        } else {
            Schema::rename($tempTable, $modelTable);

            $this->line('<info>Table created:</info> ' . $modelTable);
        }

        if (method_exists($model, 'post_migration')) {
            Schema::table($modelTable, function (Blueprint $table) use ($model) {
                $model->post_migration($table);
            });
        }
    }

    private function updateOrder()
    {
        foreach ($this->models as $table_name => $model) {
            $this->processDependencies($table_name);
        }
    }

    private function processDependencies($table_name)
    {
        $orders = [];

        if (!empty($this->models[$table_name]['dependencies']) && !$this->models[$table_name]['processed']) {

            foreach ($this->models[$table_name]['dependencies'] as $dependency) {
                $this->processDependencies($dependency);

                array_push($orders, $this->models[$dependency]['order']);
            }

            if (!empty($orders)) {

                sort($orders);

                $order = (int)array_pop($orders) + 1;

                if ($order) {
                    $this->models[$table_name]['order'] = $order;
                }
            }
        }

        $this->models[$table_name]['processed'] = true;
    }
}
