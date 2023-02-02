<?php

namespace Mybizna\Automigrator\Traits;

use Faker\Generator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

trait HasNewFactory
{
    use HasFactory;

    protected static function newFactory()
    {
        $newFactory = new class extends Factory
        {
            public static $definition;

            public function definition()
            {
                $definition = static::$definition;

                return $definition($this->faker);
            }
        };

        $newFactory::$definition = function (Generator $faker) {
            return (new static)->definition($faker);
        };

        $newFactory::guessModelNamesUsing(function () {
            return get_called_class();
        });

        return $newFactory;
    }
}
