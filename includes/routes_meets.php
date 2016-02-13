<?php

$app->group('/meets', function() use ($app, $db) {

    # Get a list of people who are available (Connect screen)
    $app->get("/available", function() use ($app, $db) {
        $user = authenticate($app);

        # Exclude people who you have invited
        $excludes = array();
        $query = $db->v2_meets_invited()->where("invitor_id", $user->id);
        foreach ($query as $row) array_push($excludes, $row["invitee_id"]);

        # Exclude people who have invited you
        $query = $db->v2_meets_invited()->where("invitee_id", $user->id);
        foreach ($query as $row) array_push($excludes, $row["invitor_id"]);

        # Exclude people you have blocked
        $query = $db->blocked()->where("blocked_by", $user->id);
        foreach ($query as $row) array_push($excludes, $row["blocked_id"]);

        $data = array();

        # Get a list of available users
        $query = $db->users()->where("available_until IS NOT NULL")->and("id != ?", $user->id);

        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to get a list of available users"]));
        }

        foreach ($query as $row) {

            # If you have invited the person - skip him
            if (in_array($row["id"], $excludes)) continue;

            $user_row = $user->view($row["id"]);
            $user_row["available_until"] = strtotime($row["available_until"]);
            $user_row["available_for_minutes"] = strtotime($row["available_until"]) - time();
            array_push($data, $user_row);
        }

        return $app->response->write(json_encode($data));
    });

    # Invite a user for a meeting
    $app->post('/invite/:userId', function($userId) use ($app, $db) {
        $user = authenticate($app);

        $invitor_id = $user->id;
        $invitee_id = $userId;

        # Are you available?
        $is_available = $db->users()->where("id", $user->id)->fetch()["available_until"];
        if ($is_available == null) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Your availability is off - switch it on"]));
        }

        # Are you inviting the same user?
        $result = $db->v2_meets_invited()->where("invitor_id", $invitor_id)->where("invitee_id", $invitee_id)->fetch();
        if ($result) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"User already invited"]));
        }

        # Has the invitees available time passed?
        $invitee_time = $db->users()->where("id", $invitee_id)->fetch()["available_until"];
        if (strtotime($invitee_time) < time()) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Invitee's availability has passed"]));
        }

        # Is there already a meet between these people?
        $result = $db->v2_meets_matched();
        foreach ($result as $row) {

            # Filter rows that are for these two people
            if (
                (( $row["invitor_id"]==$invitor_id)&&($row["invitee_id"]==$invitee_id)
                ||($row["invitor_id"]==$invitee_id)&&($row["invitee_id"]==$invitor_id))
                &&(strtotime($row["end_time"]) > time())
            ){
                return $app->response->write(json_encode(["status"=>"error", "message"=>"Users already have a meet matched"]));
            }
        }

        # Check if the user you're inviting has already invited you
        # If that's the case - generate an entry in `v2_meets_matched`
        $result = $db->v2_meets_invited()->where("invitor_id", $invitee_id)->where("invitee_id", $invitor_id)->fetch();
        if ($result) {

            # In this case, $invitee_id is the person who invited you first
            $data["invitor_id"] = $invitee_id;
            $data["invitee_id"] = $invitor_id;

            # Meet will end when the earlier availability ends
            $invitor_available_until = strtotime($db->users()->where("id", $data["invitor_id"])->fetch()["available_until"]);
            $invitee_available_until = strtotime($db->users()->where("id", $data["invitee_id"])->fetch()["available_until"]);

            if ($invitor_available_until < $invitee_available_until) {
                $data["end_time"] = date("Y-m-d H:i:s", $invitor_available_until);
            } else {
                $data["end_time"] = date("Y-m-d H:i:s", $invitee_available_until);
            }

            # Grab all "oncampus" locations from `locations`
            $location_ids = array();
            $location_rows = $db->locations()->where("category", "oncampus");
            foreach ($location_rows as $row) {
                array_push($location_ids, $row["id"]);
            }

            # Pick a random location id
            $id = mt_rand(0, count($location_ids) - 1);
            $data["location"] = $location_ids[$id];

            # Insert the data in `v2_meets_matched`
            $query = $db->v2_meets_matched()->insert($data);
            if (!$query) {
                return $app->response->write(json_encode([
                    "status"=>"error",
                    "message"=>"Unable to insert data for a new meet. Call the developer at once!!"
                ]));
            }

            # Set both people as unavailable
            $data = array("available_until" => null);
            $query = $db->users()->where("id", $invitor_id)->or("id", $invitee_id);

            if (!$query) return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to select users"]));

            foreach ($query as $row) {
                $update = $db->users()->where("id", $row["id"])->update($data);

                if (!$update) {
                    return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to set availability to 0"]));
                }
            }

            # Delete all other invitations for both people
            $query = $db->v2_meets_invited()->where("invitor_id", $invitor_id)
                                            ->or("invitor_id", $invitee_id)
                                            ->or("invitee_id", $invitor_id)
                                            ->or("invitee_id", $invitee_id)
                                            ->delete();

            if (!$query) {
                return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to delete other invitations"]));
            }

            # Send push notification to the other person
            $this_user_name = $db->users()->where("id", $invitor_id)->fetch()["name"];
            $res = push_notification($invitee_id, "New Meet!", $this_user_name. " wants to meet you", "meet");

            if ($res->code != 200) {
                return $app->response->write(json_encode(["status"=>"issue", "data" => $data, "message"=>"Meet generated - push notification failed"]));
            }

            return $app->response->write(json_encode(["status"=>"ok", "message"=>"User has already invited you. Meet generated."]));
        }

        # Else - you're the first to suggest a meeting
        # So we make an entry in `meets_invited`
        $data = array(
            "invitor_id"  => $invitor_id,
            "invitee_id"  => $invitee_id
        );

        $insert = $db->v2_meets_invited()->insert($data);

        # If insertion was successful
        if ($insert) {

            # Send push notification to the other guy
            $this_user_name  = $db->users()->where("id", $invitor_id)->fetch()["name"];
            $res = push_notification($invitee_id, "New Meet!", $this_user_name. " wants to meet you", "invite");

            return $app->response->write(json_encode(["status"=>"ok", "message"=>"User invited for a meet."]));
        } else {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to invite user."]));
        }

    });

    # Get current meet
    $app->get('/get', function() use ($app, $db) {
        $user = authenticate($app);

        $return_data = array();

        # Get the latest meet of the user
        $row = $db->v2_meets_matched()->where("invitor_id", $user->id)
                                       ->or("invitee_id", $user->id)
                                       ->order("end_time DESC")->fetch();

        # No meet?
        if (!$row) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"You do not have any meets yet"]));
        # Else, if your last meet has already passed
        } elseif (strtotime($row["end_time"]) < time()) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"No current meets"]));
        }


        $return_data["id"]             = $row["id"];
        $return_data["your_id"]        = $row["invitor_id"] == $user->id ? $row["invitor_id"] : $row["invitee_id"];
        $return_data["your_name"]      = $db->users()->where("id", $user->id)->fetch()["name"];
        $return_data["partner_id"]     = $row["invitor_id"] == $user->id ? $row["invitee_id"] : $row["invitor_id"];
        $return_data["end_time"]       = $row["end_time"];
        $return_data["generated_time"] = $row["created"];
        $return_data["partner"]        = $user->view($return_data["partner_id"]);

        # Has the meet been confirmed by you and the invitor?
        if ($row["invitor_id"] == $user->id) {
            if ($row["invitor_confirmed"] == 1) {
                $return_row["confirmed_by_you"] = 1;
            } else {
                $return_row["confirmed_by_you"] = 0;
            }
            if ($row["invitee_confirmed"] == 1) {
                $return_row["confirmed_by_other"] = 1;
            } else {
                $return_row["confirmed_by_other"] = 0;
            }
        } else if ($row["invitee_id"] == $user->id) {
            if ($row["invitor_confirmed"] == 1) {
                $return_row["confirmed_by_other"] = 1;
            } else {
                $return_row["confirmed_by_other"] = 0;
            }
            if ($row["invitee_confirmed"] == 1) {
                $return_row["confirmed_by_you"] = 1;
            } else {
                $return_row["confirmed_by_you"] = 0;
            }
        }

        # Get location-related information
        $loc_entry_db = $db->locations()->where("id", $row["location"])->fetch();
        $return_row["location_id"]          = $row["location"];
        $return_row["location_name"]        = $loc_entry_db["location_name"];
        $return_row["location_type"]        = $loc_entry_db["type"];
        $return_row["location_phone"]       = $loc_entry_db["phone"];
        $return_row["location_website"]     = $loc_entry_db["website"];
        $return_row["location_descr"]       = $loc_entry_db["descr"];
        $return_row["lat"]                  = $loc_entry_db["lat"];
        $return_row["lng"]                  = $loc_entry_db["lng"];
        $return_row["walking_minutes"]      = $loc_entry_db["walking_minutes"];
        $return_row["location_address"]     = $loc_entry_db["address"];
        $return_row["location_rating"]      = $loc_entry_db["rating"];
        $return_row["location_img"]         = $loc_entry_db["img_url"];
        $return_row["location_discounts"]   = $loc_entry_db["discounts"];
        $return_data["location"] = $return_row;

        # Retrieve messages for the particular meet
        $messages = [];
        $msg_db = $db->messages()->where("meets_id", $row["id"]);

        foreach ($msg_db as $msg_row) {
            $author_db = $db->users()->where("id", $msg_row["author"])->fetch();
            $author = [
                "name"     => $author_db["name"],
                "surname"  => $author_db["surname"],
                "studying" => $author_db["studying"],
                "img_url"  => $author_db["img_url"]
            ];
            $msg = [
                "author"    => $author,
                "content"   => $msg_row["content"],
                "is_read"   => $msg_row["is_read"],
                "timestamp" => $msg_row["timestamp"]
            ];
            $messages[] = $msg;
        }
        $return_data["messages"] = $messages;

        # Count the number of unread messages
        $unread_count = count($db->messages()->where("meets_id", $row["id"])
            ->and("author != ?", $user->id)
            ->and("is_read", 0));

        $return_data["unread_count"] = $unread_count;

        return $app->response->write(json_encode($return_data));

    });

    # Get previous meets
    $app->get('/get-previous', function() use ($app, $db) {
        $user = authenticate($app);

        $data = array();

        # Get all meets with this user
        $query = $db->v2_meets_matched()->where("invitor_id", $user->id)->or("invitee_id", $user->id)->order("end_time DESC");
        foreach ($query as $row) {

            # if this is the current meet - skip it
            if (strtotime($row["end_time"]) > time()) continue;

            $return_data["id"]             = $row["id"];
            $return_data["your_id"]        = $row["invitor_id"] == $user->id ? $row["invitor_id"] : $row["invitee_id"];
            $return_data["partner_id"]     = $row["invitor_id"] == $user->id ? $row["invitee_id"] : $row["invitor_id"];
            $return_data["end_time"]       = $row["end_time"];
            $return_data["generated_time"] = $row["created"];
            $return_data["partner"]        = $user->view($return_data["partner_id"]);

            # Has the meet been confirmed by you and the invitor?
            if ($row["invitor_id"] == $user->id) {
                if ($row["invitor_confirmed"] == 1) {
                    $return_row["confirmed_by_you"] = 1;
                } else {
                    $return_row["confirmed_by_you"] = 0;
                }
                if ($row["invitee_confirmed"] == 1) {
                    $return_row["confirmed_by_other"] = 1;
                } else {
                    $return_row["confirmed_by_other"] = 0;
                }
            } else if ($row["invitee_id"] == $user->id) {
                if ($row["invitor_confirmed"] == 1) {
                    $return_row["confirmed_by_other"] = 1;
                } else {
                    $return_row["confirmed_by_other"] = 0;
                }
                if ($row["invitee_confirmed"] == 1) {
                    $return_row["confirmed_by_you"] = 1;
                } else {
                    $return_row["confirmed_by_you"] = 0;
                }
            }

            # Get location-related information
            $loc_entry_db = $db->locations()->where("id", $row["location"])->fetch();
            $return_row["location_id"]          = $row["location"];
            $return_row["location_name"]        = $loc_entry_db["location_name"];
            $return_row["location_type"]        = $loc_entry_db["type"];
            $return_row["location_phone"]       = $loc_entry_db["phone"];
            $return_row["location_website"]     = $loc_entry_db["website"];
            $return_row["location_descr"]       = $loc_entry_db["descr"];
            $return_row["lat"]                  = $loc_entry_db["lat"];
            $return_row["lng"]                  = $loc_entry_db["lng"];
            $return_row["walking_minutes"]      = $loc_entry_db["walking_minutes"];
            $return_row["location_address"]     = $loc_entry_db["address"];
            $return_row["location_rating"]      = $loc_entry_db["rating"];
            $return_row["location_img"]         = $loc_entry_db["img_url"];
            $return_row["location_discounts"]   = $loc_entry_db["discounts"];

            # Retrieve messages for the particular meet
            $messages = [];
            $msg_db = $db->messages()->where("meets_id", $row["id"]);

            foreach ($msg_db as $msg_row) {
                $author_db = $db->users()->where("id", $msg_row["author"])->fetch();
                $author = [
                    "name"     => $author_db["name"],
                    "surname"  => $author_db["surname"],
                    "studying" => $author_db["studying"],
                    "img_url"  => $author_db["img_url"]
                ];
                $msg = [
                    "author"    => $author,
                    "content"   => $msg_row["content"],
                    "is_read"   => $msg_row["is_read"],
                    "timestamp" => $msg_row["timestamp"]
                ];
                $messages[] = $msg;
            }
            $return_row["messages"] = $messages;

            # Count the number of unread messages
            $unread_count = count($db->messages()->where("meets_id", $row["id"])
                ->and("author != ?", $user->id)
                ->and("is_read", 0));
            $return_row["unread_count"] = $unread_count;

            $return_data["location"] = $return_row;

            array_push($data, $return_data);
        }

        return $app->response->write(json_encode($data));

    });

    # Get people who invited you (Invites page)
    $app->get('/get-interested', function() use ($app, $db) {
        $user = authenticate($app);
        $return_data = [];

        # Get info about all users who invited you
        $meets_data = $db->v2_meets_invited()->where("invitee_id", $user->id);

        foreach ($meets_data as $row) {

            # If the user is no longer available - skip him
            $availability = $db->users()->where("id", $row["invitor_id"])->fetch()["available_until"];
            if ($availability == null) continue;

            $return_row = [];

            # Get info about the person who wants to meet us
            $partner_id = $row["invitor_id"];
            $partner_data = $db->users()->where("id", $partner_id)->fetch();

            $return_row["id"]       = $partner_data["id"];
            $return_row["name"]     = $partner_data["name"];
            $return_row["surname"]  = $partner_data["surname"];
            $return_row["img_url"]  = $partner_data["img_url"];
            $return_row["studying"] = $partner_data["studying"];
            $return_row["bio"]      = $partner_data["bio"];
            $return_row["country"]  = $partner_data["country"];

            array_push($return_data, $return_row);
        }

        return $app->response->write(json_encode($return_data));

    });

    # Confirm a meet
    $app->post('/confirm/:meetid', function($meetId) use ($app, $db) {
        $user = authenticate($app);

        # Get the meet from the database
        $meet_entry = $db->v2_meets_matched()->where("id", $meetId)->fetch();

        # If this user is the invitor
        if ($meet_entry["invitor_id"] == $user->id) {

            # Check if you have already confirmed the meet
            $row = $db->v2_meets_matched()->where("id", $meetId)->fetch();
            if ($row["invitor_confirmed"] == 1) {
                return $app->response->write(json_encode(["status"=>"error", "message"=>"You have already confirmed this meet"]));
            }

            $query = $meet_entry->update(array("invitor_confirmed" => 1));
            if ($query) {
                return $app->response->write(json_encode(["status"=>"ok", "message"=>"Meet confirmed"]));
            }
        }

        # If this user is the invitee
        if ($meet_entry["invitee_id"] == $user->id) {

            # Check if you have already confirmed the meet
            $row = $db->v2_meets_matched()->where("id", $meetId)->fetch();
            if ($row["invitee_confirmed"]) {
                return $app->response->write(json_encode(["status"=>"error", "message"=>"You have already confirmed this meet"]));
            }

            $query = $meet_entry->update(array("invitee_confirmed" => 1));
            if ($query) {
                return $app->response->write(json_encode(["status"=>"ok", "message"=>"Meet confirmed"]));
            }
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to confirm meet"]));

    });

    # Cancel a meet
    $app->post('/cancel/:meetid', function($meetId) use ($app, $db) {
        $user = authenticate($app);

        # Get the meet from the database
        $row = $db->v2_meets_matched()->where("id", $meetId)->fetch();

        if (!$row) return $app->response->write(json_encode(["status"=>"error", "message"=>"No meet found"]));

        # New row for `meets_cancelled`
        $new_row = array();

        # Determine who cancelled the meet
        if ($row["invitor_id"] == $user->id) {
            $new_row["invitor_cancelled"] = 1;
            $new_row["invitee_cancelled"] = 0;
            $recipient_id = $row["invitee_id"];
        } else {
            $new_row["invitor_cancelled"] = 0;
            $new_row["invitee_cancelled"] = 1;
            $recipient_id = $row["invitor_id"];
        }

        # Copy columns
        $new_row["invitor_id"]          = $row["invitor_id"];
        $new_row["invitee_id"]          = $row["invitee_id"];
        $new_row["location"]            = $row["location"];
        $new_row["invitor_confirmed"]   = $row["invitor_confirmed"];
        $new_row["invitee_confirmed"]   = $row["invitee_confirmed"];

        # Copy the row into `meets_cancelled`
        $query = $db->v2_meets_cancelled()->insert($new_row);
        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to record cancelled meet"]));
        }

        # Delete the entry from `meets_matched`
        $query = $row->delete();
        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to delete meet from `meets_matched`"]));
        }

        # Notify the other person that the meet has been cancelled
        $canceller_name = $db->users()->where("id", $user->id)->fetch()["name"];

        $headers = array(
            "X-Parse-Application-Id" => "x2ZAqxPhLwbCHJK2nXlGHC9reZsJqGYt6oVjy4LR",
            "X-Parse-REST-API-Key" => "srNWooxU2IIwjhMNBRK88PmSOKjPx2roVyawTzN5",
            "Content-Type" => "application/json"
        );
        $body = array(
            "channels" => array(
                "user_channel_".$recipient_id
            ),
            "data" => array(
                "title" => "Meet Cancelled!",
                "alert" => $canceller_name." has just cancelled the meet with you.",
                "badge" => "Increment"
            )
        );

        # Send request
        /*Unirest\Request::post("https://api.parse.com/1/push", $headers, json_encode($body));*/

        return $app->response->write(json_encode(["status"=>"ok", "message"=>"You have cancelled the meet"]));

    });

    # Change location of an arranged meet
    $app->post('/change-location/:meetid/:locid', function($meetId, $locId) use ($app, $db) {
        $user = authenticate($app);

        # Get the meet from the database
        $meet_entry = $db->v2_meets_matched()->where("id", $meetId);

        # Change the location
        if ($meet_entry->update(array("location" => $locId))) {
            return $app->response->write(json_encode(["status"=>"ok", "message"=>"Location changed."]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to change location."]));

    });

    # Send messages to a person within a particular meet
    $app->post('/chat', function() use ($app, $db) {
        $user = authenticate($app);

        $data["meets_id"]  = trim($app->request->post('meets_id'));
        $data["author"]    = $user->id;
        $data["content"]   = trim($app->request->post('content'));
        $data["timestamp"] = time();

        # Check if message is blank
        if ($data["content"] == "") {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Blank message"]));
        }

        # Check if the meeting actually exists
        $meeting = $db->v2_meets_matched()->where("id", $data["meets_id"])->fetch();
        if (!$meeting) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"No such meeting ID"]));
        }

        # Get the recipient's id (safety case just in case)
        $data["recipient"] = 0;
        if ($meeting["invitor_id"] == $user->id) {
            $data["recipient"] = $meeting["invitee_id"];
        } else if ($meeting["invitee_id"] == $user->id) {
            $data["recipient"] = $meeting["invitor_id"];
        }

        # Check if the user has blocked you
        $query = $db->blocked()->where("blocked_by", $data["recipient"]);
        foreach ($query as $row) {
            if ($row["blocked_id"] == $user->id) {
                return $app->response->write(json_encode(["status"=>"ok", "message"=>"User is blocked by recipient"]));
            }
        }

        # Insert message into the db
        $query = $db->messages()->insert($data);

        if ($query) {

            # Return a bit more info about the message
            $author_db = $db->users()->where("id", $data["author"])->fetch();
            $data["author"] = [
                "name"     => $author_db["name"],
                "surname"  => $author_db["surname"],
                "studying" => $author_db["studying"],
                "img_url"  => $author_db["img_url"]
            ];

            # Find the id of the recipient
            $meet_row = $db->meets_matched()->where("id", $data["meets_id"])->fetch();

            if ($meet_row["invitor_id"] == $user->id) {
                $recipient_id = $meet_row["invitee_id"];
            } else {
                $recipient_id = $meet_row["invitor_id"];
            }

            # Push notification to the other person
            $res = push_notification($recipient_id, "Nom Yap", $data['author']['name'].': '.$data['content'], "message");

            if ($res->code != 200) {
                return $app->response->write(json_encode(["status"=>"issue", "data" => $data, "message"=>"Message added - push notification failed"]));
            }

            return $app->response->write(json_encode(["status"=>"ok", "data" => $data, "message"=>"Message added"]));
        }

        # If unsuccessful
        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to add message"]));

    });

    # Mark messages as read
    $app->post('/chat/mark', function() use ($app, $db) {
        $user = authenticate($app);

        $meet_id = $app->request->post('meets_id');

        # Check if the meeting ID actually exists
        $meeting = $db->v2_meets_matched()->where("id", $meet_id)->fetch();
        if (!$meeting) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"No such meeting ID"]));
        }

        # Grab all unmarked messages that are from the other person
        $messages = $db->messages()->where("meets_id", $meet_id)
            ->and("author != ?", $user->id)
            ->and("is_read", 0);

        foreach ($messages as $row) {

            $query = $row->update(array("is_read" => 1));
            if (!$query) {
                return $app->response->write(json_encode(["status"=>"error", "message"=>"No messages to mark as read"]));
            }
        }

        return $app->response->write(json_encode(["status"=>"ok", "message"=>"Messages marked as read"]));
    });

    # Report a person for a particular meet
    $app->post('/report', function() use ($app, $db) {
        $user = authenticate($app);

        $data["reporter_id"] = $user->id;
        $data["meets_id"]    = trim($app->request->post("meets_id"));
        $data["message"]     = trim($app->request->post("message"));

        # Check if message is blank
        if ($data["message"] == "") {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Blank message"]));
        }

        # Grab meeting entry
        $meeting = $db->v2_meets_matched()->where("id", $data["meets_id"])->fetch();
        if (!$meeting) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"No such meeting ID"]));
        }

        # Determine the reported id
        $data["reported_id"] = ($meeting["invitor_id"] == $user->id)
            ? (int)$meeting["invitee_id"]
            : (int)$meeting["invitor_id"];

        # Insert report into the db
        $query = $db->reports()->insert($data);

        # If unsuccessful
        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to save report into the database"]));
        }

        # Email the report to hey@nomyap.com
        # Instantiate the client.
        $mgClient = new Mailgun\Mailgun('key-bd43610842ccf69a3d9b0f1c81907abd');
        $domain = "nomyap.com";

        # Grab the reporter's name
        $reporter_row  = $db->users()->where("id", $data["reporter_id"])->fetch();
        $reporter_name = $reporter_row["name"]." ".$reporter_row["surname"];

        $reported_row = $db->users()->where("id", $data["reported_id"])->fetch();
        $reported_name = $reported_row["name"]." ".$reported_row["surname"];

        $report_message = "
            Reported user: ".$reported_name." (ID ".$data["reporter_id"].")\n
            Reported by: ".$reporter_name." (ID ".$reporter_row["id"].") \n
            Message: ".$data["message"]."
        ";

        # Make the call to the client.
        $mgClient->sendMessage($domain,
            array('from'  => 'Nom Yap <hey@nomyap.com>',
                'to'      => 'hey@nomyap.com',
                'subject' => 'New report from '.$reporter_name,
                'text'    => $report_message)
        );

        return $app->response->write(json_encode(["status"=>"ok", "message"=>"Report has been submitted"]));
    });

});
