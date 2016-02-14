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

/*$app->get("/status", function() use ($app, $APIS) {

    $headers = array("Accept" => "application/json");
    $body = array("foo" => "hellow", "bar" => "world");

    $res1 = Unirest\Request::post($APIS[0], $headers, $body);
    $res2 = Unirest\Request::post($APIS[1], $headers, $body);
    $res3 = Unirest\Request::post($APIS[2], $headers, $body);

    $code1 = $res1->body->headers->response_code;
    $code2 = $res2->body->headers->response_code;
    $code3 = $res3->body->headers->response_code;



    $response->code;        // HTTP Status code
    $response->headers;     // Headers
    $response->body;        // Parsed body
    $response->raw_body;    // Unparsed body


    $app->render("/status.html", array(
        "code1" => $code1,
        "code2" => $code2,
        "code3" => $code3
    ));
});*/

# Check the APIs every few seconds and record errors
$app->get("/check/:apiName", function($apiName) use ($app, $db, $APIS) {

    $endpoint = $db->apis()->where("name", $apiName)->fetch()["endpoint"];

    if (!$endpoint) {
        return $app->response->write("No such endpoint");
    }

    for ($i = 0; $i < 50; $i++) {
        $headers = array("Accept" => "application/json");
        $res = Unirest\Request::get($APIS[0], $headers);
        $code = $res->code;


        $data = array("code" => $code);
        $query = $db->$apiName()->insert($data);
        var_dump($query);
    }

    return $app->response->write("Done checking endpoint");
});

# Get all APIs
$app->get("/list", function() use ($app, $db) {
    $return_data = [];
    $query = $db->apis();
    foreach ($query as $row) array_push($return_data, $row["name"]);

    return $app->response->write(json_encode($return_data));
});

# Get data about a particular API
$app->get("/get/:apiName", function($apiName) use ($app, $db, $APIS) {

    # Check if API exists
    if (!$db->$apiName()->fetch()) return $app->response->write("No such endpoint");
    $data = $db->$apiName();

    $error_codes = ["404", "500"];

    $return_data = array();

    # 200
    $return_data["200"] = count($data->where("code", 200));

    # Loop through each error type
    foreach ($error_codes as $error_code) {
        $data_db = $db->$apiName()->where("code", $error_code);
        $data_return = array(
            "count" => count($data_db),
            "times" => []
        );
        foreach ($data_db as $row) {
            array_push($data_return["times"], strtotime($row["timestamp"]));
        }
        $return_data[$error_code] = $data_return;
    }

    return $app->response->write(json_encode($return_data));
});

# Get data about a particular API for the given time sample
$app->get("/get/:apiName/:from/:to", function($apiName, $from_timestamp, $to_timestamp) use ($app, $db, $APIS) {

    # Check if API exists
    if (!$db->$apiName()->fetch()) return $app->response->write("No such endpoint");
    $data = $db->$apiName();

    $error_codes = ["404", "500"];
    $return_data = array();

    $from = date("Y-m-d H:i:s", $from_timestamp);
    $to   = date("Y-m-d H:i:s", $to_timestamp);

    # 200
    $return_data["200"] = count($data->where("code", 200)->and("timestamp > ?", $from)->and("timestamp < ?", $to));

    # Loop through each error type
    foreach ($error_codes as $error_code) {
        $data_db = $db->$apiName()->where("code", $error_code)->and("timestamp > ?", $from)->and("timestamp < ?", $to);
        $data_return = array(
            "count" => count($data_db),
            "times" => []
        );
        foreach ($data_db as $row) {
            array_push($data_return["times"], strtotime($row["timestamp"]));
        }
        $return_data[$error_code] = $data_return;
    }

    return $app->response->write(json_encode($return_data));
});

# Add a new API to check
$app->post("/add-api", function() use ($app, $db) {

    $name = $app->request->post("name");
    $endpoint = $app->request->post("endpoint");

    # Create a new table for the API
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    $sql1 = "
        CREATE TABLE `".$name."` (
            `id` int(11) NOT NULL,
            `code` int(3) NOT NULL,
            `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

    ";
    $sql2 = "
        ALTER TABLE `".$name."`
        ADD PRIMARY KEY (`id`);
    ";
    $sql3 = "
        ALTER TABLE `".$name."`
        MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
    ";

    $res1 = $conn->query($sql1);
    $res2 = $conn->query($sql2);
    $res3 = $conn->query($sql3);
    var_dump($res1);
    var_dump($res2);
    var_dump($res3);

    $conn->close();

    # Is there an API with the same name?
    if ($db->apis()->where("name", $name)->fetch()) {
        $app->response->setStatus(400);
        return $app->response->write("API with such a name exists in the system");
    }

    # Add the API to the api table
    $data = array(
        "name" => $name,
        "endpoint" => $endpoint
    );
    $query = $db->apis()->insert($data);

    # If API has been added successfully
    if ($query) {
    # if (true) {

        # Append the endpoint to the Raspberry crontab
        $headers = array("Accept" => "application/json");
        $body = array("endpoint" => $endpoint);
        $res = Unirest\Request::post("http://jule.chickenkiller.com/stirhack/", $headers, $body);

        var_dump($res->body);

        if ($res) {
            return $app->response->write("API added to the system");
        }

    } else {
        $app->response->setStatus(500);
        return $app->response->write("Unable to add API to the system");
    }

});

# Send an SMS about a requested API statistics
$app->post("/sms/:apiname", function() {

});

$app->run();

