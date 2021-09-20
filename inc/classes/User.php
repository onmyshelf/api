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
     * Set password
     * @param  string  $password
     * @return boolean Success
     */
    public function setPassword(string $password)
    {
        return (new Database())->setUserPassword($this->id, $password);
    }


    /**
     * Set password reset token
     * @return boolean Success
     */
    public function setResetToken($token)
    {
        return (new Database())->setUserResetToken($this->id, $token);
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
        $data = (new Database())->getUserByName($username);
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
        $data = (new Database())->getUserByLogin($username, $password);
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
    public static function getByResetToken($token)
    {
        $data = (new Database())->getUserByResetToken($token);
        if (!$data) {
            return false;
        }

        return new self($data);
    }
}
