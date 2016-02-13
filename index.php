<?php

require_once dirname(__FILE__) . '/bootstrap.php';

$APIS = [
    "http://dogfish.tech/api/api1/lax",
    "http://dogfish.tech/api/api2/fr",
    "http://dogfish.tech/api/api3/usd,eur"
];

# Test routes
$app->get('/', function() use ($app, $db) {
    $app->render("/index.html", array());
});

$app->get('/phpinfo', function() {
    return phpinfo();
});

# Privacy page
$app->get("/status", function() use ($app, $APIS) {

    $headers = array("Accept" => "application/json");
    $body = array("foo" => "hellow", "bar" => "world");

    $res1 = Unirest\Request::post($APIS[0], $headers, $body);
    $res2 = Unirest\Request::post($APIS[1], $headers, $body);
    $res3 = Unirest\Request::post($APIS[2], $headers, $body);

    $code1 = $res1->body->headers->response_code;
    $code2 = $res2->body->headers->response_code;
    $code3 = $res3->body->headers->response_code;


    /*
    $response->code;        // HTTP Status code
    $response->headers;     // Headers
    $response->body;        // Parsed body
    $response->raw_body;    // Unparsed body
    */

    $app->render("/status.html", array(
        "code1" => $code1,
        "code2" => $code2,
        "code3" => $code3
    ));
});

$app->run();

