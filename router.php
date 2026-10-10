<?php
// Router for PHP built-in server: php -S localhost:8000 router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Never expose internals when running under the built-in server
if (preg_match('#^/(data|lib|views)/#i', $path) || preg_match('#^/(config|router)\.php$#i', $path)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve static assets
}
require __DIR__ . '/index.php';
