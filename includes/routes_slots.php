<?php

$app->group('/slots', function() use ($app, $user, $db) {

    $app->post('/add', function() use ($app, $user, $db) {
        authenticate_user($app, $user);

        # Grab parameters from the request
        $data["recurring_day"]  = (int)filter_var(trim($app->request->post('recurring_day')),   FILTER_SANITIZE_NUMBER_INT);
        $data["start_time"]     = (int)filter_var(trim($app->request->post('start_time')),      FILTER_SANITIZE_NUMBER_INT);
        $data["end_time"]       = (int)filter_var(trim($app->request->post('end_time')),      FILTER_SANITIZE_NUMBER_INT);
        # $data["length"]         = (int)filter_var(trim($app->request->post('length')),          FILTER_SANITIZE_NUMBER_INT);
        # $data["length"]         = 30;
        $data["length"]         = $data["end_time"] - $data["start_time"];
        $data["user_id"]        = $user->id;

        # If any of the parameters are incorrect - return an error
        if ($data["recurring_day"] < 1
            || $data["recurring_day"] > 7
            || !$data["start_time"]
            || !$data["end_time"]
            || $data["length"] < 0
        ) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Missing or invalid parameters"]));
        }

        # Check if the input slot fits in one day (24*60)
        if (($data["start_time"] > 1440)||($data["start_time"]+$data["length"] > 1440)) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Slot does not fit in one day"]));
        }

        # Check whether the time of the new slot overlaps and existing slot
        $existing_slots = $db->slots_recurring()
            ->where("user_id", $data["user_id"])
            ->where("recurring_day", $data["recurring_day"]);

        foreach ($existing_slots as $slot) {
            $old_start  = $slot["start_time"];
            $old_end    = $old_start + $slot["length"];
            $new_start  = $data["start_time"];
            $new_end    = $new_start + $data["length"];
            /*echo "Old slot: ".$old_start." - ".$old_end."\n";
            echo "New slot: ".$new_start." - ".$new_end."\n";*/

            // Check whether the new slot overlaps an existing one
            // If either of these holds, the slot is valid
            if (($old_end <= $new_start)||($new_end <= $old_start)) {
                continue;
            } else {
                return $app->response->write(json_encode(["status"=>"error", "message"=>"Slots overlap"]));
            }
        }

        $query = $result = $db->slots_recurring()->insert($data);

        if ($query) {
            return $app->response->write(json_encode(["status"=>"ok", "message"=>"Slot Added"]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to add slot"]));
    });

    // Get all of the user's personal slots
    $app->post('/get', function() use ($app, $user, $db) {
        authenticate_user($app, $user);

        $return_data = User::get_time_slots($db, $user->id);
        return $app->response->write(json_encode($return_data));
    });

    // Edit a slot based on slot ID
    /* $app->post('/edit/:slotId', function($slotId) use ($app, $user, $db) {
        if (!$user or empty($user)) return $app->response->write(json_encode(["status"=>"error", "message"=>"Unauthorized"]));

        // Grab all changes from the POST request and save them in $data
        if (isset($post['recurring_day']))  $data['recurring_day']  = $post['recurring_day'];
        if (isset($post['start_time']))     $data['start_time']     = $post['start_time'];
        if (isset($post['length']))         $data['length']         = $post['length'];

        // Find the slot in the database and update it
        if ($db->slots_recurring()->where("id", $slotId)->update($data)) {
            $app->response->write(json_encode(["status"=>"ok", "message"=>"Slot edited."]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to update slot."]));
    }); */

    // Delete a slot based in slot ID
    $app->post('/delete/:slotId', function($slotId) use ($app, $user, $db) {
        authenticate_user($app, $user);

        $slot = $db->slots_recurring[$slotId];

        if ($slot["user_id"] != $user->id) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Trying to delete someone else's slot eh?"]));
        }

        if ($slot && $slot->delete()) {
            return $app->response->write(json_encode(["status"=>"ok", "message"=>"Slot deleted."]));
        }

        return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to delete slot."]));
    });

});
