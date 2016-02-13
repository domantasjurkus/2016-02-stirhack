# V2 - Big changes incoming!

# Deployment

You deploy to api.nomyap.com by adding a remote gitrepo to your version. To do that you must have an account on the server. Add the repo using

    git remote add live ssh://NAME@api.nomyap.com/var/repo/api.git
  
substitute NAME for your username on the server. You then deploy by pushing to the live master branch using:

    git push live master

This will automatically put the master branch on the server. Remember to keep the master branch of the github repo and the live repo in sync.

# API

Note: Every GET request has to have (str)token as a parameter in in teh header in order to identify the user.



## User Routes (V2 updated)
Method | Route                 | Parameters      | Description
------ | --------------------- | --------------- | -------
POST   | /user/register        | email, password | Registers a user (sends activation email)
GET    | /user/activate        |                 | Activates account from an email link
POST   | /user/resend          | email           | (json)win/fail
POST   | /user/email-reset-password |            | Request a password reset email
POST   | /user/reset-password  | hash, password  | Changes the users password
POST   | /user/login           | email, password | Returns token
POST   | /user/authenticate    | token           | Checks if token is valid
POST   | /user/update          | name, surname, studying, bio, country | Update user details
POST   | /user/myprofile       |                 | Returns (json)user_data
POST   | /user/profile/:userId | userId          | Returns (json)user_data
POST   | /user/upload-image    | imagefile       | (json)win/fail
POST   | /user/block/:userId   |                 | (json)win/fail
POST   | /user/unblock/:userId |                 | (json)win/fail
POST   | /user/get-blocked     |                 | Returns (array)blocked_ids
POST   | /user/available/:mins |                 | Sets the user as available
POST   | /user/reset           |                 | Sets the user as unavailable
GET    | /user/available-for   |                 | Returns availability in minutes (or NULL if unavailable)



## Meet Routes (updated)
Method | Route                  | Parameters        | Description
------ | ---------------------- | ----------------- | -------
GET    | /meets/available       |                   | Returns a list of available users
POST   | /meets/invite/:userId  |                   | Returns (json)win/fail
GET    | /meets/get             |                   | Returns current meet (if any)
GET    | /meets/get-previous    |                   | Returns a list of previous meets
GET    | /meets/get-interested  |                   | Returns a list of people who have invited you
POST   | /meets/confirm/:meetId |                   | (json)win/fail
POST   | /meets/cancel/:meetId  |                   | (json)win/fail
POST   | /meets/change-location/:meetId/:locId |    | (json)win/fail
POST   | /meets/chat            | meets_id, content | (json)win/fail
POST   | /meets/chat/mark       | meets_id,         | (json)win/fail
POST   | /meets/report          | meets_id, message | (json)win/fail



## Other Routes
Method | Route                 | Parameters      | Description
------ | --------------------- | --------------- | -------
GET    | /                     |                 | "Welcome to Nomyap API"
GET    | /phpinfo              |                 | PHP information
POST   | /locations/get        | category        | (json)location_data
POST   | /feedback             | content         | (json)win/fail

Feedback should be sent to:
