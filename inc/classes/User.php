<?php

class User
{
    protected $id;
    protected $username;
    protected $email;

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
     * Set password
     * @param  string  $password
     * @return boolean Success
     */
    public function setPassword(string $password)
    {
        return (new Database)->setUserPassword($this->id, $password);
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
        if ($this->email)
            Mailer::send($this->email, "To reset your password, please click on this link: <a href='$reset_url'>$reset_url</a>", 'Your reset password request');

        return true;
    }


    /*
     *  Static methods
     */

    /**
     * Get user object by name
     * @param  string $username
     * @return object User object
     */
    public static function getByName($username)
    {
        $data = (new Database)->getUserByName($username);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Get user object by login
     * @param  string $username
     * @param  string $password
     * @return object User object
     */
    public static function getByLogin($username, $password)
    {
        $data = (new Database)->getUserByLogin($username, $password);
        if (!$data) {
            return false;
        }

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
}
