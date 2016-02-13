<?php

# Helper functions to make the codebase a bit more tidy

# Log API requests
function log_request() {

    # Use global variables
    GLOBAL $app, $db;

    $data = array();
    $data["route"] = $app->router()->getCurrentRoute()->getPattern();
    $data["headers"] = implode(",",$app->request->headers->all());

    $query = $db->logs()->insert($data);
    if (!$query) {
        echo "WARNING: Request logging failed";
    }
}

# Checks whether two slots overlap (assuming day is the same)
function do_slots_overlap($start1, $length1, $start2, $length2) {

    $start1 = (int)$start1;
    $length1 = (int)$length1;
    $end1 = $start1 + $length1;

    $start2 = (int)$start2;
    $length2 = (int)$length2;
    $end2 = $start2 + $length2;

    // Check whether the new slot overlaps an existing one
    // If either of these holds, the slots do not overlap
    if (($end1 <= $start2)||($end2 <= $start1)) {
        return false;
    }

    return true;
}

# Find all common slots from two sets of slots
function get_common_slots($slots1, $slots2) {
    // Loop through all slots, check if they overlap
    // If they do - record them as a common time
    // Format: [[day, start_time, length], [day, start_time, length], ...]
    $common_times = [];

    foreach ($slots1 as $slot1) {
        foreach ($slots2 as $slot2) {
            /*echo "Start times: ".$slot1["start_time"]." and ".$slot2["start_time"]."\n";*/

            // If slot2 is not on the same day as slot 1 - skip it
            if ($slot1["recurring_day"] != $slot2["recurring_day"]) { continue; }

            // Helper variables - honestly I'm getting lost without these
            $day     = $slot1["recurring_day"];
            $start1  = $slot1["start_time"];
            $length1 = $slot1["length"];
            $end1    = $start1 + $length1;
            $start2  = $slot2["start_time"];
            $length2 = $slot2["length"];
            $end2    = $start2 + $length2;

            // If the slots do overlap - record a potential meet time
            if (do_slots_overlap($start1, $length1, $start2, $length2)) {

                // Now we generate a meeting 'Slot' which will have
                // a start time, end time and length that works for both users

                /**
                 * [---slot1---]        OR    [-------slot1-------]
                 *     [---slot2---]              [---slot2---]
                 */
                if ($start1 < $start2) {
                    $common_start = $start2;
                    if ($end1 < $end2) {
                        $common_end = $end1;
                    } else {
                        $common_end = $end2;
                    }

                    /**
                     *     [---slot1---]    OR        [---slot1---]
                     * [---slot2---]              [-------slot2-------]
                     */
                } else {
                    $common_start = $start1;
                    if ($end2 < $end1) {
                        $common_end = $end2;
                    } else {
                        $common_end = $end1;
                    }
                }

                // Save this overlap as a possible meeting time
                $slot_data["day"]        = $day;
                $slot_data["start_time"] = $common_start;
                $slot_data["length"]     = $common_end - $common_start;
                array_push($common_times, $slot_data);
            }
        }
    }

    return $common_times;
}

# Figures out an exact date for a meeting when given a slot (weekday and hour)
function get_nearest_timestamp($selected_slot) {
    // We now know the day of the week and hour of the meeting
    // To get an EXACT timestamp for the meeting:
    // 1) Find the timestamp for today
    // 2) Add/subtract weekday difference with the weekday of the selected slot
    // 3) Add +7 days to the timestamp if the meet cannot happen this week
    // 4) Create the final timestamp that takes year, month and day
    //    from the old timestamp and hour from the selected_slot

    // 1. Find the timestamp for today
    $timestamp = time();

    // 2. Add/subtract the difference between the timestamps weekday with the selected slot's weekday
    $timestamp_weekday = date('N', $timestamp);
    $dif = $selected_slot["day"] - $timestamp_weekday;
    $timestamp = strtotime('+'.$dif.' day', $timestamp);
    /*echo $timestamp."\n";*/

    // 3. If we went a few days back - we cannot meet this week
    //    so we add 7 days to push the timestamp to next week
    if ($timestamp < time()) {
        $timestamp = strtotime('+7 day', $timestamp);
    }

    // So now $timestamp has correct year, month and day

    // 4) Create the final timestamp that takes year, month and day from the old timestamp,
    //    hour, minute and second from the selected_slot (or just leave seconds = 0)
    $timestamp_info = getdate($timestamp);
    /*var_dump($timestamp_info);*/

    $yr = $timestamp_info["year"];
    $mt = $timestamp_info["mon"];
    $dy = $timestamp_info["mday"];
    $hr = $selected_slot["start_time"]/60;
    $mn = $selected_slot["start_time"]%60;
    $sc = 0;

    // And here's our timestamp
    $final_timestamp = mktime($hr, $mn, $sc, $mt, $dy, $yr);

    // Last check: if the timestamp is for today, we need to check if it has passed already
    // If it has - we move to the next week
    if ($final_timestamp < time()) {
        $final_timestamp = strtotime('+7 day', $final_timestamp);
    }

    return $final_timestamp;
}

# Send push notification to Parse API
function push_notification($recipient_id, $title, $message, $notif_type) {

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
            "title" => $title,
            "alert" => $message,
            "badge" => "Increment",
            "type"  => $notif_type
        )
    );

    # Send request
    return Unirest\Request::post("https://api.parse.com/1/push", $headers, json_encode($body));
}

# Send an email request to Mailgun
function email_request($email, $title, $message) {

    $mgClient = new Mailgun\Mailgun('key-bd43610842ccf69a3d9b0f1c81907abd');
    $domain = "nomyap.com";

    # Make the call to the client.
    $mgClient->sendMessage($domain,
        array('from'  => 'Nom Yap <hey@nomyap.com>',
            'to'      => $email,
            'subject' => $title,
            'text'    => $message)
    );

}