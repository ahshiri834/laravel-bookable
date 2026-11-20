<?php

namespace Ahshiri\Bookable\Tests\Support;

/**
 * Switches the test app to MySQL when DB_CONNECTION=mysql (used by CI and the concurrency test).
 * Otherwise the default in-memory SQLite connection from Testbench is used.
 */
final class ConfiguresDatabase
{
    public static function usingMysql(): bool
    {
        return getenv('DB_CONNECTION') === 'mysql';
    }

    public static function apply($app): void
    {
        if (! self::usingMysql()) {
            $app['config']->set('database.default', 'testing');

            return;
        }

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'bookable_test',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
    }
}
