<?php
declare(strict_types=1);

namespace App\Controllers;

/** Liveness probe for hosting platforms: proves the web server and PHP are up. Deliberately does not touch the database. */
final class HealthController
{
    public function live(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo "ok";
    }
}
