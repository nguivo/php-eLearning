<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use PDO;

class DatabaseServiceProvider extends AppServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PDO::class, function (Container $c): PDO {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s', config('database.driver'),
                config('database.host'),
                config('database.port'),
                config('database.name'),
                config('database.charset'),
            );

            return new PDO($dsn, config('database.user'), config('database.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        });
    }

}