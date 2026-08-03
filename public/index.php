<?php

use App\Kernel;

// Compatible avec les deux structures : "public/index.php" + "vendor/" au-dessus (local),
// ou "index.php" + "vendor/" au même niveau (hébergement sans document root personnalisable).
$vendorAutoload = is_file(__DIR__.'/vendor/autoload_runtime.php')
    ? __DIR__.'/vendor/autoload_runtime.php'
    : dirname(__DIR__).'/vendor/autoload_runtime.php';

require_once $vendorAutoload;

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
