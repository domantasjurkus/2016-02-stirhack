<?php

$app->group('/user', function() use ($app, $user, $db) {

    $app->post('/register', function() use ($app, $db) {

        # Get variables from POST request
        $email    = $app->request->post('email');
        $password = $app->request->post('password');

        # Check if email is valid
        if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            return $app->response->write(json_encode(["status"=>"error", "message" => "Invalid email supplied: " . $email]));
        }

        # Create a new User instance
        $user = new User();
        if ($user->exists($email)) {

            # Check if the user is activated
            $activated = $db->users()->where("email", $email)->fetch()["activated"];

            return $app->response->write(json_encode(["status"=>"error", "message" => "User already registered"]));
        } else {
            $result = $user->create($email, $password);
            if ($result) {
                return $app->response->write(json_encode(["status"=>"ok", "message" => "Account registered"]));
            } else {
                return $app->response->write(json_encode(["status"=>"error", "message" => "Unable to register user"]));
            }
        }
    });

    # Activate an account from an email link
    $app->get('/activate', function() use ($app, $db) {

        # Get variables from POST request
        $hash  = filter_var(trim($app->request->get('hash')),  FILTER_SANITIZE_STRING);

        # Get user row from DB
        $user_row = $db->users()->where('activation_hash', $hash)->fetch();

        # If there is no user with this hash
        if (!$user_row) {
            return $app->response->write(USER_ERROR_MARKUP);
        }

        # If the user is already activated
        if ($user_row['activated'] == 1) {
            return $app->response->write(ALREADY_ACTIVE_MARKUP);
        }

        # If the user has not been activated
        else if ($user_row['activated'] == 0) {
            $data = array('activated'=>1);

            # Email a welcome message
            $mgClient = new Mailgun\Mailgun('key-bd43610842ccf69a3d9b0f1c81907abd');
            $domain = "nomyap.com";

            # Make the call to the client.
            $mgClient->sendMessage($domain,
                array('from'  => 'Nom Yap <hey@nomyap.com>',
                    'to'      => $user_row["email"],
                    'subject' => "Welcome to Nom Yap!",
                    'text'    => welcome_email_message())
            );

            # Set 'active' to 1 for the appropriate email
            $db->users()->where('activation_hash', $hash)->update($data);
            return $app->response->write(USER_ACTIVATED_MARKUP);

        } else {
            return $app->response->write(json_encode(["status"=>"error", "message" => "Unable to activate account"]));
        }

    });

    # Resend verification link
    $app->post('/resend', function() use ($app, $db) {

        # Get variables from POST request
        $email = filter_var($app->request->post('email'), FILTER_SANITIZE_EMAIL);

        # Check if email is valid
        if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            return $app->response->write(json_encode(["status"=>"error", "message" => "Invalid email supplied: " . $email]));
        }

        # If the account is already activated - no need to resend
        $is_activated = $db->users()->where('email', $email)->fetch()["activated"];

        if ($is_activated) {
            return $app->response->write(json_encode(["status"=>"error", "message" => "Account is already activated"]));
        };

        $hash = $db->users()->where('email', $email)->fetch()["activation_hash"];
        User::send_activation_email($email, $hash);
        return $app->response->write(json_encode(["status"=>"ok", "message" => "Verification link resent"]));


    });

    # Request a password reset email
    $app->post('/email-reset-password', function() use ($app, $db) {

        $email = $app->request->post("email");

        $data = array("reset_hash" => sha1(time()));
        $query = $db->users->where("id", User::getByEmail($db, $email))->update($data);
        if (!$query) return $app->response->write(json_encode(["status"=>"error", "message" => "Unable to save reset hash"]));

        $link = API_HOST."/reset/".$data["reset_hash"];

        email_request($email, PASSWORD_RESET_EMAIL_TITLE, reset_email_message($link));

        return $app->response->write(json_encode(["status"=>"ok", "message" => "Password reset email will be sent shortly"]));
    });

    # Change the password after reset (Called from the website, not from the app)
    $app->post('/reset-password', function() use ($app, $db) {

        # Grab POST parameters
        $hash = $app->request->post("hash");
        $new_password = $app->request->post("password");

        # Generate new password hash
        $pw_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $data = array();
        $data["password"]           = $pw_hash;
        $data["token"]              = null;
        $data["token_expiration"]   = time();
        $data["reset_hash"] = null;

        $row = $db->users()->where("reset_hash", $hash)->fetch();
        $query = $row->update($data);
        if (!$query) return $app->response->write(json_encode(["status"=>"error", "hash" => $hash, "message" => "Unable to save new password"]));

        $app->render("/reset-success.html");
        return 0;
    });

    # Login (get a token)
    $app->post('/login', function() use ($app, $db) {
        # log_request();

        $email      = filter_var(trim($app->request->post('email')), FILTER_SANITIZE_EMAIL);
        $password   = filter_var(trim($app->request->post('password')), FILTER_SANITIZE_STRING);

        $user = new User();
        $token = $user->login($email, $password);

        # Check if username and password is valid
        if ($token) {

            $user_id = $db->users->where("email", $email)->fetch()["id"];

            # Check if user is activated
            $data = $db->users()->select('activated')->where('email', $email)->fetch();
            if ($data['activated'] != "1") {
                return $app->response->write(json_encode(["status"=>"error", "user_id"=>$user_id, "message" => "Account has not been activated"]));
            };

            # Check if profile is set up
            $data = $db->users()->select('profile_setup')->where('email', $email)->fetch();
            if ($data['profile_setup'] != "1") {
                return $app->response->write(json_encode(["status"=>"to-change", "user_id"=>$user_id, "message"=>"Profile not set up", "token"=>$token]));
            };

            return $app->response->write(json_encode(["token"=>$token, "user_id"=>$user_id]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message" => "Wrong username or password"]));
    });

    # Authenticate the user with a token (for auto-logging)
    $app->post('/authenticate', function() use ($app, $db) {
        $user = authenticate($app);

        if (!$user or empty($user)) {
            return $app->response->write(json_encode(["status"=>"ok", "message" => "Automatic login failed. Must log in manually."]));
        }
        return $app->response->write(json_encode(["status"=>"ok", "message" => "User already logged in"]));
    });

    $app->post('/update', function() use ($app, $db) {
        $user = authenticate($app);

        $post = $app->request->patch();
        $data = array();

        # $app->response->write(json_encode($post));

        # Grab all changes from the POST request and save them in $data
        if (isset($post['name']))       $data['name']     = $post['name'];
        if (isset($post['surname']))    $data['surname']  = $post['surname'];
        if (isset($post['studying']))   $data['studying'] = $post['studying'];
        if (isset($post['level']))      $data['level']    = $post['level'];
        if (isset($post['bio']))        $data['bio']      = $post['bio'];
        if (isset($post['country']))    $data['country']  = $post['country'];
                                        $data['profile_setup']  = 1;

        # $app->response->write($post['studying']);

        # If we were able to update the user fields
        $update = $user->update($user->id, $data);
        if ($update) {
            return $app->response->write(json_encode(["status"=>"ok", "message"=>"Account updated successfully"]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Nothing to update"]));
    });

    $app->post('/myprofile', function() use ($app, $db) {
        $user = authenticate($app);

        $data = $user->view($user->id);
        return $app->response->write(json_encode(array('status'=>!empty($data)?"ok":"error", 'result'=>$data)));
    });

    $app->post('/profile/:userId', function($userId) use ($app, $db) {
        $user = authenticate($app);

        $data = $user->view($userId);
        return $app->response->write(json_encode($data));
    });

    # Upload profile picture
    $app->post('/upload-image', function() use ($app, $db) {
        $user = authenticate($app);

        # Check size
        $img_obj = $_FILES["image"];
        if ($img_obj["size"] >= 10485760) {
            return $app->response()->write(json_encode(["status"=>"error","message"=>"Image file too large, must be less than 10MB"]));
        }

        # Init Intervention
        $manager = new Intervention\Image\ImageManager(array('driver' => 'gd'));
        $img = $img_obj['tmp_name'];

        # Set memory limit to unlimited for processing the image
        # Also, orient the picture
        ini_set('memory_limit', '-1');

        $img = $manager->make($img);
        $img->fit(200, 200);
        $img->orientate();

        # Save image
        $filename = md5(time());
        $img->save("img/user/".$filename.".jpg");

        # Reset the memory limit
        ini_set('memory_limit', '256M');

        # Delete the previous image
        $img_hash = $db->users()->where("id", $user->id)->fetch()["img_hash"];
        if ($img_hash){
            try {
                # Delete
                unlink("img/user/" . $img_hash . ".jpg");
                /*echo "Image unlinked";*/

                # If there is no previous hash - oh well
            } catch (Exception $e) {
                /*echo "Unable to delete old avatar";*/
            }

        }

        # Update DB
        $data = array(
            "img_url" => API_HOST.'/img/user/'.$filename.'.jpg',
            "img_hash" => $filename
        );
        $query = $db->users()->where("id", $user->id)->update($data);

        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to assign 'img_url' in `users`"]));
        }

        return $app->response()->write(json_encode(["status"=>"ok","message"=>"Image uploaded successfully"]));
    });

    # Block a user
    $app->post('/block/:blockId', function($blockId) use ($app, $db) {
        $user = authenticate($app);

        # Check if the same user is not being blocked again
        $query = $db->blocked()->where("blocked_by", $user->id)->and("blocked_id", $blockId)->fetch();
        if ($query) {
            return $app->response->write(json_encode(array("status"=>"error", "message"=>"User already blocked")));
        }

        $data = array(
            "blocked_by" => $user->id,
            "blocked_id" => $blockId
        );

        # Insert data in `blocked` table
        $query = $db->blocked()->insert($data);
        if ($query) {
            return $app->response->write(json_encode(array("status"=>"ok", "message"=>"User blocked")));
        }

        $app->response->setStatus(500);
        return $app->response->write(json_encode(array("status"=>"error", "message"=>"Unable to block user")));
    });

    # Unblock a user
    $app->post('/unblock/:userId', function($userId) use ($app, $db) {
        $user = authenticate($app);

        # Check if user is unblocking someone not blocked
        $query = $db->blocked()->where("blocked_by", $user->id)->and("blocked_id", $userId)->fetch();
        if (!$query) {
            return $app->response->write(json_encode(array("status"=>"error", "message"=>"User has not been blocked")));
        }

        $query = $db->blocked()->where("blocked_by", $user->id)->and("blocked_id", $userId)->delete();
        if ($query) {
            return $app->response->write(json_encode(array("status"=>"ok", "message"=>"User unblocked")));
        }

        return $app->response->write(json_encode(array("status"=>"error", "message"=>"Unable to unblock user")));
    });

    # Get a list of blocked users
    $app->post('/get-blocked', function() use ($app, $db) {
        $user = authenticate($app);

        $blocked_ids = array();
        $rows = $db->blocked()->where("blocked_by", $user->id);
        foreach ($rows as $row) {
            array_push($blocked_ids, $row["blocked_id"]);
        }

        return $app->response->write(json_encode($blocked_ids));
    });

    # Set you availability
    $app->post("/available/:minutes", function($minutes) use ($app,  $db) {
        $user = authenticate($app);

        # If you have a meet right now - set the meet end time to now
        # This will make the meet 'in the past'
        $query = $db->v2_meets_matched()->where("invitor_id", $user->id)->or("invitee_id", $user->id);
        foreach ($query as $row) {
            if (strtotime($row["end_time"]) > time()) {

                $data = array("end_time" => date("Y-m-d H:i:s", time()));
                $query = $row->update($data);
                if (!$query) {
                    return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to end current meet"]));
                }
            }
        }

        $data = array();
        $data["available_until"] = date("Y-m-d H:i:s", strtotime("+".$minutes." minute", time()));

        # Update everyone's availability
        $query = $db->users()->where("available_until IS NOT NULL");
        foreach ($query as $row) {

            # If available time has passed
            if (strtotime($row["available_until"]) < time()) {
                $data = array("available_until" => null);
                $update = $db->users()->where("id", $row["id"])->update($data);
                if (!$update) {
                    return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to update availability of other users"]));
                }
            }
        }

        $query = $db->users()->where("id", $user->id)->update($data);
        if ($query) {
            return $app->response->write(json_encode(["status"=>"ok", "message"=>"User available"]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to set availability"]));
    });

    # Disable availability (before logging off)
    $app->post("/reset", function() use ($app, $db) {
        $user = authenticate($app);

        # Do you have a meet right now?
        /*$query = $db->v2_meets_matched()->where("invitor_id", $user->id)->or("invitee_id", $user->id);
        foreach ($query as $row) {
            if (strtotime($row["end_time"]) > time()) {
                # If you have a meet and you're logging off - ?
                # return $app->response->write(json_encode(["status"=>"error", "message"=>"You already have a meet right now"]));
            }
        }*/

        $data = array();
        $data["available_until"] = null;

        # Reset availability
        $query = $db->users()->where("id", $user->id)->update($data);
        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Availability is not set"]));
        }

        # Delete current meet invitations
        $db->v2_meets_invited()->where("invitor_id", $user->id)->or("invitee_id", $user->id)->delete();

        return $app->response->write(json_encode(["status"=>"ok", "message"=>"Availability reset"]));
    });

    # Is this user currently available? (discard later)
    $app->get("/is-available", function() use ($app, $db) {
        $user = authenticate($app);

        $availability = $db->users()->where("id", $user->id)->fetch()["available_until"] != null;

        return $app->response->write(json_encode($availability));
    });

    $app->get("/available-for", function() use ($app, $db) {
        $user = authenticate($app);

        $availability = $db->users()->where("id", $user->id)->fetch()["available_until"];
        if ($availability == null) $return = null;
        else $return = floor((strtotime($availability) - time())/60);

        return $app->response->write(json_encode($return));
    });



    # Delete Omar's account (FOR PRESENTATION)
    $app->get('/delete', function() use ($app, $db) {

        # Get Omar's account DB row
        $user_row = $db->users()->where("email", "2027205t@student.gla.ac.uk")->fetch();
        $id = $user_row["id"];

        # Delete all slots and messages
        $db->slots_recurring()->where("user_id", $id)->delete();
        $db->messages()->where("author", $id)->delete();

        # Delete user row
        $query = $db->users()->where("id", $id)->delete();

        if ($query) {
            return $app->response->write(json_encode(["status"=>"done", "message"=>"User deletion completed"]));
        }

        return $app->response->write(json_encode(["status"=>"ehh?", "message"=>"Deletion didn't work - maybe the account is deleted already?"]));
    });

});
