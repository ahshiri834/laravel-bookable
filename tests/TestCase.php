<?php

namespace Ahshiri\Bookable\Tests;

use Ahshiri\Bookable\BookableServiceProvider;
use Ahshiri\Bookable\Tests\Support\ConfiguresDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BookableServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        ConfiguresDatabase::apply($app);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Start every test from an empty schema (matters on MySQL, where tables persist).
        Schema::dropAllTables();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(1);
            $table->timestamps();
        });
    }
}
