<?php

namespace DummyNamespace;

use Faker\Generator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Legodion\Lucid\Traits\HasNewFactory;

class DummyClass extends Authenticatable
{
    use HasApiTokens, HasNewFactory, Notifiable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    }

    public function definition(Generator $faker)
    {
        return [
            'name' => $faker->firstName(),
            'email' => $faker->unique()->safeEmail(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'created_at' => $faker->dateTimeThisMonth(),
        ];
    }
}
