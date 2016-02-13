<?php

# Send email
$app->get('/email', function() {

    $mgClient = new Mailgun\Mailgun('key-bd43610842ccf69a3d9b0f1c81907abd');
    $domain = "nomyap.com";

    # Make the call to the client.
    $mgClient->sendMessage($domain,
        array('from'  => 'Nom Yap <hey@nomyap.com>',
            'to'      => $email,
            'subject' => $title,
            'text'    => $message)
    );

});