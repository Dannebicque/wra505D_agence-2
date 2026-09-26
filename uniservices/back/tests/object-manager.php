<?php

/*
 * Gestionnaire d'entités que PHPStan lit pour connaître le vrai mapping Doctrine, types Carbon
 * compris. Il démarre le noyau sans ouvrir de connexion à la base.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
