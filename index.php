<?php

namespace Miakiwi\Kwlnk;

use Dotenv\Dotenv;
use Miakiwi\Kwlnk\App\Config;
use Miakiwi\Kwlnk\App\Logger;
use Pecee\SimpleRouter\SimpleRouter;



// Load the Composer autoloader
require_once 'private' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';



// Load the environment variables
$dotenv = Dotenv::createImmutable('private');
$dotenv->load();



// Load the application configuration
Logger::get()->debug("Loading application configuration from file", [
    'file' => $_ENV['APP_CONFIG']
]);

Config::load($_ENV['APP_CONFIG']);



// Import the routes
require_once 'private' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'routes.php';



// Start the router
Logger::get()->debug("---------- Received request ----------", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI']
]);

SimpleRouter::start();