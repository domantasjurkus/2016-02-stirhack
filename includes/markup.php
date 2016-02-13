<?php

# If a user hash cannot be found
define('USER_ERROR_MARKUP', '

<style>
    *{font-family:consolas;}
    body {
        background: #C90006;
        color: #FFFFFF;
    }
</style>
<h2>Whoops! We can\'t find that user.</h2>
<h4>Please help us out and notify the developers!</h4>

');

# If the user is already activated
define('ALREADY_ACTIVE_MARKUP', '

<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
        body {
            background: #c90006;
            color: #ffffff;
            font-family:sans-serif;
        }
        img {
            width: 30%;
            height: auto;
        }
        </style>
    </head>
    <body>
        <center>
            <img src="https:#api.nomyap.com/img/etc/nom_yap_logo.png" /><br />
            <h2>Account already activated!</h2>
        </center>
    </body>
</html>

');

# IF the user has just been activated
define('USER_ACTIVATED_MARKUP', '

<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet" type="text/css">
        <style>
        body {
            background: #c90006;
            color: #ffffff;
            font-family: "Lato", sans-serif;
        }
        img {
            width: 30%;
            height: auto;
        }
        </style>
    </head>
    <body>
        <center>
            <img src="https://api.nomyap.com/img/etc/nom_yap_logo.png" /><br />
            <h2>Your account<br />has been activated!</h2><br /><br />
            Open Nom Yap now<br />and continue...
        </center>
    </body>
</html>

');

# Page for deleting Omar's account
define('USER_DELETE_MARKUP', '

<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet" type="text/css">
        <style>
        body {
            background: #c90006;
            color: #ffffff;
            font-family: "Lato", sans-serif;
        }
        img {
            width: 30%;
            height: auto;
        }
        </style>
    </head>
    <body>
        <center>
            <img src="https://api.nomyap.com/img/etc/nom_yap_logo.png" /><br />
            <form action="'.API_HOST.'/user/delete" method="get">
                <button type="submit">Delete 2027205t@student.gla.ac.uk</button><br>
            </form>
        </center>
    </body>
</html>

');

# Page for uploading new location info
define('LOCATION_ADD_MARKUP', '

<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet" type="text/css">
        <style>
        body {
            background: #c90006;
            color: #ffffff;
            font-family: "Lato", sans-serif;
        }
        </style>
    </head>
    <body>
        <img src="https://api.nomyap.com/img/etc/nom_yap_logo.png" height="200"/><br />
        <h3>New Location</h3>
        <form id="form" action="'.API_HOST.'/locations/add" method="post" enctype="multipart/form-data"><br/>
            <input type="text" name="location_name"  placeholder="Location Name"><label>Name</label><br/>
            <input type="text" name="type"           placeholder="Type">         <label>Type</label><br/>
            <input type="text" name="category"       placeholder="Category">     <label>Category</label><br/>
            <input type="text" name="phone"          placeholder="Phone">        <label>Phone</label><br>
            <input type="text" name="website"        placeholder="Website">      <label>Website</label><br/>
            <input type="text" name="desc"           placeholder="Description">  <label>Description</label><br/>
            <input type="text" name="lat"            placeholder="Latitude">     <label>Latitude</label><br/>
            <input type="text" name="lng"            placeholder="Longitude">    <label>Longitude</label><br/>
            <input type="text" name="address"        placeholder="Address">      <label>Address</label><br/>
            <input type="text" name="rating"         placeholder="Rating">       <label>Rating</label><br/>

            <input type="file" name="image_file"    accept="image/*"><br/>
            <input type="Submit">
        </form>


    </body>
</html>

');