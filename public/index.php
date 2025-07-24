<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Bootstrap\EnvironmentLoader;

EnvironmentLoader::load();

$app = new App();
$app->run();
