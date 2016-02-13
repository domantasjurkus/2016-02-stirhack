<?php

# Most (eventually all) things email related

define("PASSWORD_RESET_EMAIL_TITLE", "Reset Nom Yap Account");

# Email message for activating a user
function activation_email_message($link) {
    return "Hey!\n\nThanks for signing up for Nom Yap.\n\nTo activate your account, please verify your email address by clicking the following link:\n\n".$link."\n\nMany thanks!\n\nNom Yap";
}

# Email message after a user has been activated
function welcome_email_message() {
    return "

        Hello there, welcome to the app!

    ";
}

# Email message for resetting a password
function reset_email_message($link) {
    return "Hey!\n\n
    A request to reset you password has been sent. If you wish to do so, click on the link bellow:\n\n
    ".$link."\n\n
    If you did not make this request then no worries - your password will stay the same.\n\n
    Many thanks for using the app!\n\n
    Nom Yap";
}