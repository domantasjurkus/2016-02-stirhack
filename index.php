<?php

require_once dirname(__FILE__) . '/bootstrap.php';

function auth_check($app) {
    $token = filter_var(trim($app->request->post('token')));
    $user = new User();
    $id = $user->getByToken($token);
    return $id['id'] > 0 ? $user : false;
}

$user = auth_check($app);

function authenticate_user($app, $user) {

    # Log each request
    log_request();

    if (!$user or empty($user)) {
        $app->response->setStatus(401);
        $app->stop();
    }
    return $user;
}

# New authentication function for extracting tokens from headers
function authenticate($app) {

    # Grab token from request header
    $token = $app->request->headers("Token");
    $user = new User();

    # If a user with such a token exists - return user object
    $id = $user->getByToken($token);
    if ($id["id"] > 0) return $user;

    # Else - stop the request
    $app->response->setStatus(401);
    $app->stop();

    return 0;
}

require_once dirname(__FILE__) . '/includes/routes_user.php';
//require_once dirname(__FILE__) . '/includes/routes_slots.php';
require_once dirname(__FILE__) . '/includes/routes_meets.php';
require_once dirname(__FILE__) . '/includes/routes_locations.php';

# Test routes
$app->get('/', function() use ($app, $db) {
    // echo "Welcome to Nomyap API v2";

    echo password_hash("123", PASSWORD_DEFAULT);
});

$app->get('/phpinfo', function() { return phpinfo(); });

$app->post('/feedback', function() use ($app, $db) {
    $user = authenticate($app);

    $data = array();
    $data['author']  = $user->id;
    $data['content'] = $app->request->params('content');

    // Check if content is not empty
    if (!$data['content']) {
        return $app->response->write(json_encode(['status'=>'error','message'=>'No feedback content']));
    }

    $user_row = $db->users()->where("id", $user->id)->fetch();
    $name = $user_row["name"]." ".$user_row["surname"];

    $mgClient = new Mailgun\Mailgun('key-bd43610842ccf69a3d9b0f1c81907abd');
    $domain = "nomyap.com";

    # Make the call to the client.
    $mgClient->sendMessage($domain,
        array('from'  => 'hey@nomyap.com',
            'to'      => 'hey@nomyap.com',
            'subject' => 'Feedback from '.$name,
            'text'    => $data['content'])
    );

    // Save the feedback in the database
    $query = $db->feedback()->insert($data);
    if ($query) {
        return $app->response->write(json_encode(['status'=>'ok','message'=>'Feedback saved']));
    }

    return $app->response->write(json_encode(['status'=>'error','message'=>'Unable to send feedback']));
});

# Page for viewing user info
$app->get('/9bc65c2abec141778ffaa729489f3e87', function() use ($app, $user, $db) {
    /*
    $query = $db->users();
    $users = array();
    foreach ($query as $row) {
        if (!$row["name"]) continue;
        $user = array();

        array_push($user, $row["name"]);
        array_push($user, $row["surname"]);
        array_push($user, $row["email"]);
        array_push($user, $row["banned"] ? "Banned" : "-");

        array_push($users, $user);
    }

    $app->render('users.html', array(
        'users' => $users
    ));
    */
});

# ---------- Views ----------

# Page for uploading new locations
$app->get('/d5189de027922f81005951e6efe0efd5', function() use ($app, $user, $db) {
    return $app->response->write(LOCATION_ADD_MARKUP);
});

# Page for demo account deletion
$app->get('/d4466cce49457cfea18222f5a7cd3573', function() use ($app){
    return $app->response->write(USER_DELETE_MARKUP);
});

# Privacy page
$app->get("/privacy", function() use ($app) {
    $app->render("/privacy.html");
});

# Reset password pages
$app->get("/reset/:hash", function($hash) use ($app, $db) {

    # Check if the user has a password reset hash
    $has_hash = $db->users->where("reset_hash", $hash)->fetch();
    if (!$has_hash) {
        $app->render("/reset-error.html", array(
            "api"  => API_HOST,
            "message" => "Error: this link does not work anymore"
        ));
    } else {
        $app->render("/reset.html", array(
            "api"  => API_HOST,
            "hash" => $hash
        ));
    }

});

$app->run();

