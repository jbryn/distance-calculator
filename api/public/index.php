<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Jbryn\Downriver\AppFactory;
use Jbryn\Downriver\Config\Settings;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

// Values from the .env file take precedence, then real environment variables (e.g. in a container)
$settings = Settings::fromEnvironment($_ENV + $_SERVER + getenv());

AppFactory::create($settings)->run();
