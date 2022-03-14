<?php

namespace DummyNamespace;

use Legodion\Lucid\Traits\HasNewFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class DummyClass extends Model
{
    use HasNewFactory;

    protected $guarded = [];

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
