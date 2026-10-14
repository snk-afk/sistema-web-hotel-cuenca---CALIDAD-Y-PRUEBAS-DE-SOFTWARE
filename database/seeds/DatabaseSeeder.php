<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Support\Config;
use PDO;
use RuntimeException;

final class DatabaseSeeder
{
    private PDO $db;
    private string $env;

    public function __construct(PDO $db, string $env = 'development')
    {
        $this->db = $db;
        $this->env = strtolower($env);
    }

    public function run(): void
    {
        $app_env = strtolower(Config::string('APP_ENV', 'development'));
        if ($app_env === 'production' && !in_array($this->env, ['production', 'prod'], true)) {
            throw new RuntimeException('El seeder de desarrollo está bloqueado en producción.');
        }

        if ($this->env === 'production' || $this->env === 'prod') {
            $seeder = new ProductionSeeder($this->db);
            $seeder->run();
        } else {
            $seeder = new DevelopmentSeeder($this->db);
            $seeder->run();
        }
    }
}
