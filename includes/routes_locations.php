<?php

$app->group('/locations', function() use ($app, $user, $db) {

    # Return a list of all locations
    $app->post('/get', function() use ($app, $user, $db) {
        $user = authenticate($app);

        $cat = filter_var(trim($app->request->post('category')), FILTER_SANITIZE_STRING);
        if (!$cat) $cat = "all";

        if ($cat == "all") {
            $loc_data = $db->locations();
        } else {
            $loc_data = $db->locations()->where("category", $cat);
        }

        $return_data = [];
        foreach ($loc_data as $row) {
            $return_row = [];

            $return_row["id"]               = $row["id"];
            $return_row["location_name"]    = $row["location_name"];
            $return_row["type"]             = $row["type"];
            $return_row["category"]         = $row["category"];
            $return_row["phone"]            = $row["phone"];
            $return_row["website"]          = $row["website"];
            $return_row["description"]      = $row["descr"];
            $return_row["lat"]              = $row["lat"];
            $return_row["lng"]              = $row["lng"];
            $return_row["walking_minutes"]  = $row["walking_minutes"];
            $return_row["address"]          = $row["address"];
            $return_row["rating"]           = $row["rating"];
            $return_row["img_url"]          = $row["img_url"];
            $return_row["discounts"]        = $row["discounts"];

            array_push($return_data, $row);
        }

        return $app->response->write(json_encode($return_data));

    });

    # Upload info about a new location
    $app->post('/add', function() use ($app, $user, $db) {

        $data["location_name"] = trim($app->request->post('location_name'));
        $data["type"]          = trim($app->request->post('type'));
        $data["category"]      = trim($app->request->post('category'));
        $data["phone"]         = trim($app->request->post('phone'));
        $data["website"]       = trim($app->request->post('website'));
        $data["lat"]           = trim($app->request->post('lat'));
        $data["lng"]           = trim($app->request->post('lng'));
        $data["address"]       = trim($app->request->post('address'));
        $data["rating"]        = trim($app->request->post('rating'));
        $data["descr"]         = trim($app->request->post('desc'));

        $img_obj               = $_FILES["image_file"];
        $data["img_url"]       = $img_obj["name"];

        # Insert data into `locations`
        $query = $db->locations()->insert($data);
        if (!$query) {
            return $app->response->write(json_encode(["status"=>"error", "message"=>"Unable to insert data into the database"]));
        }

        # Check image size
        if ($img_obj["size"] >= 10485760) {
            return $app->response()->write(json_encode(["status"=>"error","message"=>"Image file too large, must be less than 10MB"]));
        }

        # Init Intervention
        $manager = new Intervention\Image\ImageManager(array('driver' => 'gd'));
        $img = $img_obj['tmp_name'];

        # Set memory limit to unlimited for processing the image
        ini_set('memory_limit', '-1');
        $img = $manager->make($img);

        # Save image
        $filename = $img_obj["name"];
        $img->fit(200, 200);
        $img->save("img/locations/".$filename);

        # Reset the memory limit
        ini_set('memory_limit', '128');

        return $app->response->write("Okay, all good");
    });

});