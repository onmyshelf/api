<?php

class User
{
    protected $id;
    protected $role;
    protected $username;
    protected $enabled;
    protected $email;
    protected $firstname;
    protected $lastname;
    protected $avatar;

    public function __construct($data)
    {
        // affect properties from $data
        foreach (array_keys(get_object_vars($this)) as $p) {
            if (isset($data[$p])) {
                $this->$p = $data[$p];
            }
        }
    }


    /**
     * Get user ID
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Get username
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }


    /**
     * Get email
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }


    /**
     * Dump user
     * @return array User
     */
    public function dump()
    {
        return get_object_vars($this);
    }


    /**
     * Set password
     * @param  string  $password
     * @return boolean Success
     */
    public function setPassword(string $password)
    {
        $result = (new Database)->updateUser($this->id, ['password' => $password]);
        if (!$result) {
            return false;
        }

        // revoke all reset password tokens
        (new Database)->deleteUserTokens($this->id, 'resetpassword');

        return $result;
    }


    /**
     * Create token
     * @param  string $type
     * @return string Token
     */
    public function createToken($type=null)
    {
        $token = Token::generate();

        if ($type == 'resetpassword') {
            $lifetime = 120;
        } else {
            $lifetime = TOKEN_LIFETIME;
        }

        $expiration = time() + $lifetime * 60;
        Logger::debug("New token for user ".$this->username.": $token, expires: $expiration");

        // TODO: check if behind reverse proxy; add trusted proxies config
        $ipOrigin = (string)$_SERVER['REMOTE_ADDR'];

        // add token in database
        if (!(new Database)->createToken($token, $this->id, $expiration, $ipOrigin, $type)) {
            Logger::error('Failed to create token for user '.$this->id);
            return false;
        }

        return $token;
    }


    /**
     * Create reset password request
     *
     * @return bool Success
     */
    public function resetPassword()
    {
        // create reset token for user
        $token = $this->createToken('resetpassword');
        if (!$token) {
            return false;
        }

        $reset_url = Config::getHomeUrl()."/resetpassword?token=$token";

        // log request
        Logger::message("*************  RESET PASSWORD REQUEST  *************");
        Logger::message("*  UserID: ".$this->id);
        Logger::message("*  URL:    $reset_url");
        Logger::message("****************************************************");

        // send reset URL to user
        if ($this->email) {
            Mailer::send(
                $this->email,
                "<p>Dear ".$this->username.",</p>To reset your password, please click on this link:<br /><a href='$reset_url'>$reset_url</a>",
                "Your reset password request"
            );
        }

        return true;
    }


    /**
     * Update user profile
     * @param  array   $data
     * @return boolean
     */
    public function update($data)
    {
        // remove non allowed data
        $allowed = get_object_vars($this);
        unset($allowed['id']);

        // filter data to update
        $allowed = array_keys($allowed);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
                continue;
            }

            switch ($key) {
                case 'username':
                    if (!self::validateUsername($data[$key])) {
                        return false;
                    }
                    $data[$key] = strtolower($data[$key]);
                    break;

                case 'email':
                    if (!self::validateEmail($data[$key])) {
                        return false;
                    }
                    $data[$key] = strtolower($data[$key]);
                    break;
            }
        }

        // disabled: revoke tokens
        if (isset($data['enabled']) && !$data['enabled']) {
            (new Database)->deleteUserTokens($this->id);
        }

        // update user in database
        return (new Database)->updateUser($this->id, $data);
    }


    /**
     * Delete user
     * @return bool Success
     */
    public function delete()
    {
        // revoke tokens
        (new Database)->deleteUserTokens($this->id);

        // delete user's collections
        foreach (Collection::dumpAll($this->id) as $c) {
            $collection = Collection::getById($c['id']);
            if ($collection) {
                $collection->delete();
            }
        }
        
        return (new Database)->deleteUser($this->id);
    }


    /*
     *  Static methods
     */

    /**
     * Get all users
     *
     * @return array
     */
    public static function dumpAll()
    {
        return (new Database)->getUsers();
    }


    /**
     * Get user object by ID
     * @param  int    $id
     * @return object User object
     */
    public static function getById($id)
    {
        $data = (new Database)->getUserById($id);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Get user object by login
     * @param  string $login    Username or email
     * @param  string $password (optional)
     * @return object User object
     */
    public static function getByLogin($login, $password = null)
    {
        $data = (new Database)->getUserByLogin($login);
        if (!$data) {
            return false;
        }

        // check if user is enabled
        if (!$data['enabled']) {
            return false;
        }

        // check password
        if (!is_null($password)) {
            if (!password_verify($password, $data['password'])) {
                return false;
            }
        }

        // remove password
        unset($data['password']);

        return new self($data);
    }


    /**
     * Get user object by reset token
     * @param  string $token
     * @return object User object
     */
    public static function getByToken($token, $type=null)
    {
        $data = (new Database)->getUserByToken($token, $type);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Create user
     * @param  array $data
     * @return int   User ID, FALSE if error
     */
    public static function create($data)
    {
        // defines allowed data fields
        $allowed = [
            'role',
            'username',
            'password',
            'enabled',
            'email',
            'firstname',
            'lastname',
            'avatar',
        ];

        // remove non allowed data
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        // check username
        if (!self::validateUsername($data['username'])) {
            return false;
        }
        $data['username'] = strtolower($data['username']);

        // check email
        if (isset($data['email'])) {
            if (!self::validateEmail($data['email'])) {
                return false;
            }
            $data['email'] = strtolower($data['email']);
        }

        // creates user in database
        $id = (new Database)->createUser($data);
        if (!$id) {
            Logger::error("Failed to create user");
            return false;
        }

        $data['id'] = $id;
        return new self($data);
    }


    private static function validateUsername($username)
    {
        return ctype_alnum($username);
    }


    private static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
