<?php

require_once dirname(__FILE__) . '/bootstrap.php';

# Test routes
$app->get('/', function() use ($app, $db) {
    $app->render("/index.html", array());
});

$app->get('/phpinfo', function() {
    return phpinfo();
});

# Privacy page
$app->get("/status", function() use ($app) {
    $app->render("/status.html");
});

$app->run();

