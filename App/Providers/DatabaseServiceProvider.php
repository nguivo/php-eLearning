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
                '%s:host=%s;port=%s;dbname=%s;charset=%s', $_ENV['DB_DRIVER']  ?? 'mysql',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME'] ?? 'elearning',
                $_ENV['DB_CHARSET'] ?? 'utf8mb4'
            );

            return new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        });
    }

}