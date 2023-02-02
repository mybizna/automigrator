# Automigrator

This package allows you to declare database migrations and factory definitions inside of your Laravel models.

Running the `automigrator:migrate` command will automatically apply any changes you've made inside your `migration` methods to the database via Doctrine DBAL. If using the `HasNewFactory` trait and `definition` method, it will use the returned array inside the `definition` method to seed with when using the `-s` option.

The `automigrator:migrate` command will also run your file-based (traditional) Laravel migrations first, and then your model method migrations after. If you need your model-based migrations to run in a specific order, you may add a `$migrationOrder` property to your models with an integer value (default is `0`).

## Installation

Require this package via Composer:

```console
composer require mybizna/automigrator
```

## Usage

Use the `HasNewFactory` trait, and declare `migration` and `definition` methods in your models:

```php
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Mybizna\Automigrator\Traits\HasNewFactory;

class MyModel extends Model
{
    use HasNewFactory;

    protected $guarded = [];
    protected $migrationOrder = 1; // optional

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    }

    public function definition(Generator $faker)
    {
        return [
            'name' => $faker->name(),
            'created_at' => $faker->dateTimeThisMonth(),
        ];
    }
}
```

## Commands

### Migrating

Apply the changes inside your `migration` methods to your database:

```console
php artisan automigrator:migrate {--f|--fresh} {--s|--seed}
```

Use the `-f` option for fresh migrations, and/or the `-s` option to run seeders afterwards.

### Making Models

Create a model containing the `migration` and `definition` methods:

```console
php artisan automigrator:model {name} {--r|--resource}
```

Use the `-r` option to create a Laravel Nova resource for the model at the same time.

### Making Nova Resources

Create a Laravel Nova resource without all the comments:

```console
php artisan automigrator:resource {name} {--m|--model}
```

Use the `-m` option to create a model for the Nova resource at the same time.
