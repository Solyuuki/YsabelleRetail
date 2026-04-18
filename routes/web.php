<?php

foreach ([
    __DIR__.'/storefront.php',
    __DIR__.'/auth.php',
    __DIR__.'/admin.php',
] as $routeFile) {
    require $routeFile;
}
