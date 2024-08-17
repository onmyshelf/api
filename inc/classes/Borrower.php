<?php

class Borrower
{
    protected $id;
    protected $userId;
    protected $firstname;
    protected $lastname;
    protected $email;
    protected $owner;
    protected $visibility;

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
     * Get owner
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
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
     * Dump borrower
     * @return array Borrower
     */
    public function dump()
    {
        return get_object_vars($this);
    }


    /**
     * Update borrower info
     * @param  array   $data
     * @return boolean
     */
    public function update($data)
    {
        // remove non allowed data
        $allowed = get_object_vars($this);
        unset($allowed['id']);
        $allowed = array_keys($allowed);

        foreach (array_keys($data) as $key) {
            // remove non allowed data
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
                continue;
            }

            switch ($key) {
                case 'email':
                    if ($data[$key]) {
                        if (!User::validateEmail($data[$key])) {
                            Logger::debug("Bad email: ".$data[$key]);
                            return false;
                        }
                        $data[$key] = strtolower($data[$key]);
                    }
                    break;

                case 'visibility':
                    if (!Visibility::validateLevel($data[$key])) {
                        Logger::debug("Bad visibility level: ".$data[$key]);
                        return false;
                    }
                    break;
            }
        }

        return (new Database)->updateBorrower($this->id, $data);
    }


    /**
     * Delete borrower
     * @return bool Success
     */
    public function delete()
    {
        return (new Database)->deleteBorrower($this->id);
    }


    /*
     *  Static methods
     */

    /**
     * Get all borrowers
     *
     * @return array
     */
    public static function dumpAll()
    {
        return (new Database)->getBorrowers();
    }


    /**
     * Get borrower by ID
     * @param  int    $id
     * @return object User object
     */
    public static function getById($id)
    {
        $data = (new Database)->getBorrowerById($id);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Create borrower
     * @param  array $data
     * @return int   Borrower ID, FALSE if error
     */
    public static function create($data)
    {
        // defines allowed data fields
        $allowed = [
            'userId',
            'firstname',
            'lastname',
            'email',
            'owner',
            'visibility',
        ];

        foreach (array_keys($data) as $key) {
            // remove non allowed data
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
                continue;
            }

            // check values
            switch ($key) {
                case 'email':
                    if ($data['email']) {
                        if (!User::validateEmail($data['email'])) {
                            return false;
                        }
                        $data['email'] = strtolower($data['email']);
                    }
                    break;

                case 'visibility':
                    if (!Visibility::validateLevel($data[$key])) {
                        Logger::debug("Create borrower error: bad visibility level: ".$data[$key]);
                        return false;
                    }
                    break;
            }
        }

        return (new Database)->createBorrower($data);
    }
}
