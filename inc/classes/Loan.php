<?php

class Loan
{
    protected $id;
    protected $itemId;
    protected $state;
    protected $lent;
    protected $returned;
    protected $borrower;
    protected $notes;
    protected $date;


    public function __construct($data=null)
    {
        // affect properties from $data
        foreach (array_keys(get_object_vars($this)) as $p) {
            if (isset($data[$p])) {
                $this->$p = $data[$p];
            }
        }
    }


    public function dump()
    {
        $dump = get_object_vars($this);
        unset($dump['itemId']);

        return $dump;
    }


    /**
     * Update load
     * @param  array   $data
     * @return boolean
     */
    public function update($data)
    {
        // filter data
        $allowed = get_object_vars($this);

        // do not permit to update ID or item ID
        unset($allowed['id']);
        unset($allowed['itemId']);

        // remove unallowed data
        $allowed = array_keys($allowed);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        return (new Database)->updateLoan($this->id, $data);
    }


    /**
     * Deletes loan
     * @return bool Success
     */
    public function delete()
    {
        return (new Database)->deleteLoan($this->id);
    }


    /********************
    *  STATIC METHODS  *
    ********************/

    /**
     * Get by ID
     * @param  int $id Loan ID
     * @return object  Item object
     */
    public static function getById($id)
    {
        if (is_null($id)) {
            Logger::error("Called Item::getById(null)");
            return false;
        }

        $data = (new Database)->getLoan($id);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Creates a loan
     * @param  int    $itemId
     * @param  array  $data
     * @return int    Loan ID, FALSE if error
     */
    public static function create($itemId, $data=[])
    {
        // check required data
        $required = ['state', 'borrower'];
        foreach ($required as $key) {
            if (!isset($data[$key])) {
                Logger::error("Failed to create operation; missing: $key");
                return false;
            }
        }

        // remove unallowed data
        $allowed = ['state', 'lent', 'returned', 'borrower', 'notes'];
        unset($allowed['id']);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        // force item ID
        $data['itemId'] = $itemId;

        // create in database
        return (new Database)->createLoan($data);
    }


    public static function getStates()
    {
        return [
            "asked",
            "rejected",
            "accepted",
            "lent",
            "returned",
        ];
    }
}
