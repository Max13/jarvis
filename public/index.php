<?php

$route = $_SERVER['REQUEST_URI'];
$artisan = __DIR__ . '/../jarvis';
$headers = getallheaders();
$json = null;

if (!isset($headers['Content-Type'])) {
    $headers['Content-Type'] = null;
}

if (strpos($headers['Content-Type'], 'application/json') !== false) {
    $json = file_get_contents('php://input');
} else {
    header('Content-Type: application/json', true, 501);
    die('"Not Implemented"');
}

if ($route === '/telegram/psZVnLQJ6fI6r0nK6Nb6OuicEfZXWpcR') {
    file_put_contents(
        __DIR__.'/../log.log',
        $route.PHP_EOL.$json.PHP_EOL,
        FILE_APPEND
    );

    exec(
        $artisan.' telegram:incoming '.escapeshellarg($json),
        $output,
        $result_code
    );

    if ($result_code === 0) {
        file_put_contents(__DIR__.'/../log.log', PHP_EOL, FILE_APPEND);

        header('Content-Type: application/json', true, 202);
        die('"Accepted"');
    } else {
        file_put_contents(
            __DIR__.'/../log.log',
            $result_code.' - '.json_encode($output).PHP_EOL.PHP_EOL,
            FILE_APPEND
        );

        header('Content-Type: application/json', true, 200);
        die('"OK"');
    }
} else {
    header('Content-Type: application/json', true, 404);
    die('"Not Found"');
}

header('Content-Type: application/json', true, 500);
die('"Internal server error"');
