<?php

class User {

    # Static fields
    public static $dbs;

    # Instance fields
    private $db, $data;
    public $id;

    # Constructor
    function __construct() {
        require_once dirname(__FILE__) . '/config.php';
        # Get rid of the eventually
        $this->db = new NotORM(new PDO(DB_METHOD.DB_NAME, DB_USERNAME, DB_PASSWORD));

        # Initialise the static database variable
        self::$dbs = new NotORM(new PDO(DB_METHOD.DB_NAME, DB_USERNAME, DB_PASSWORD));
    }

    # Send activation email
    public static function send_activation_email($email, $hash) {
        $link = API_HOST."/user/activate?hash=".$hash;

        # Instantiate the client.
        $mgClient = new Mailgun\Mailgun('key-bd43610842ccf69a3d9b0f1c81907abd');
        $domain = "nomyap.com";

        # Make the call to the client.
        $mgClient->sendMessage($domain,
            array('from'  => 'Nom Yap <hey@nomyap.com>',
                'to'      => $email,
                'subject' => "Verify Your Email",
                'text'    => activation_email_message($link))
        );

    }

    public static function generate_activation_hash($email) { return md5($email.time()); }

    public static function getByEmail($db, $email) { return $db->users()->where("email", $email)->fetch(); }

    public function get($id) {
        $this->data = $this->db->users()->where('id', $id)->fetch();
        return $this->data;
    }

    public function getAll() { return $this->db->users()->fetch(); }

    /*public function getByEmail($email) { return $this->db->users()->where('email', $email)->fetch(); }*/

    public function getByToken($token) {

        # Note: this function is invoked on almost every API request
        if (empty($token)) return false;

        $user = $this->db->users()->where("token = ?", $token)->where("token_expiration > NOW()")->fetch();
        $this->id = $user['id'];
        return $user;
    }

    public function exists($email) { return $this->db->users()->where('email', $email)->fetch(); }

    public function check_password($id, $password) { return password_verify($password, $this->get($id)['password']); }

    public function create($email, $password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $activation_hash = self::generate_activation_hash($email);

        // Try to send activation email here
        // DB has an 'activated' field that is 0 by default
        self::send_activation_email($email, $activation_hash);

        $u = array(
            'email'            => $email,
            'password'         => $password_hash,
            'activation_hash'  => $activation_hash,
            'token'            => bin2hex(openssl_random_pseudo_bytes(16)),
            'token_expiration' => date('Y-m-d H:i:s')
        );
        $result = $this->db->users()->insert($u);
        if ((bool)$result){
            return $this->login($email, $password);
        }
        return false;
    }

    public function update($userId, $fields) {

        # Update fields in the `users` table
        $u = $this->db->users()->where("id", $userId);
        $u->fetch();
        return $u->update($fields);
    }

    public function login($email, $password) {

        # Logs in the user class as well
        if (!$this->exists($email)) {
            return false;
        }

        $user_row = User::getByEmail(self::$dbs, $email);
        $id = $user_row["id"];



        # If password is invalid
        if (!$this->check_password($id, $password)) {
            return false;
        }

        $token_expiration_string = $user_row["token_expiration"];
        $token_expiration = strtotime($token_expiration_string);

        # Check if token has not expired
        if ($token_expiration > time()) {
            return $user_row["token"];
        }

        # Else - generate a new token and set a new expiration date
        $result = array();
        $result['token']            = bin2hex(openssl_random_pseudo_bytes(16));
        $result['token_expiration'] = date('Y-m-d H:i:s', strtotime('+7 day'));

        $query = $this->update($id, $result);

        if ($query) return $result['token'];
        return false;
    }

    public function view($userId) {
        $u = $this->get($userId);
        if (empty($u) or !$u) return $u;
        $result = array(
            'img_url'   => $u['img_url'],
            'id'        => $u['id'],
            'name'      => $u['name'],
            'surname'   => $u['surname'],
            'studying'  => $u['studying'],
            'level'     => $u['level'],
            'bio'       => $u['bio'],
            'country'   => $u['country']
        );
        /*$result['feedback'] = array();
        foreach($this->db->feedback()->where('recipient', $userId)->limit(5) as $f) {
            $author = $this->get($f['author']);
            $author = array('id'=>$author['id'], 'name'=>$author['name'], 'surname'=>$author['surname']);
            $result['feedback'][$f['id']]['author'] = $author;
            $result['feedback'][$f['id']]['content'] = $f['content'];
            $result['feedback'][$f['id']]['created'] = $f['created'];
        }*/
        /*
         * echo $application->author["name"] . "\n"; // get name of the application author
                foreach ($application->application_tag() as $application_tag) { // get all tags of $application
                    echo $application_tag->tag["name"] . "\n"; // print the tag name
}
         */
        return $result;
    }

    public function get_activation_hash($userId) {

        $u = $this->get($userId);
        if (empty($u) or !$u) return $u;

        foreach($this->db->users()->where('id', $userId) as $f) {
            $result = $f['activation_hash'];
        }

        return $result;
    }

    # Returns a multi-array of time slots [["id", "day", "start", "length"], [...], [...]]
    /*public static function get_time_slots($db, $user_id){

        $existing_slots = $db->slots_recurring()->where("user_id = ?", $user_id);
        $return_data = [];

        foreach ($existing_slots as $slot) {

            $slot_data["id"]            = $slot["id"];
            $slot_data["recurring_day"] = $slot["recurring_day"];
            $slot_data["start_time"]    = $slot["start_time"];
            $slot_data["end_time"]      = $slot["end_time"];
            $slot_data["length"]        = $slot["length"];

            array_push($return_data, $slot_data);
        }

        return $return_data;
    }*/

}
