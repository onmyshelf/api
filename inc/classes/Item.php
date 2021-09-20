<?php

class Item
{
    protected $id;
    protected $collectionId;
    protected $name;
    protected $fields;

    public function __construct($collectionId, $id=null)
    {
        $this->id = $id;
        $this->collectionId = $collectionId;
        $this->fields = [];
    }


    /*
     * Getters
     */

    /**
     * Get item ID
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Get field value(s)
     * @param  string $name Field name
     * @return mixed        Value or array of values
     */
    public function getField($name)
    {
        return (new Database())->getItemField($this->collectionId, $this->id, $name);
    }


    /*
     * Setters
     */

    /**
     * Set item name
     * @param string $name
     * @return bool  Success
     */
    public function setName(string $name)
    {
        return (new Database())->setItemName($this->id, $name);
    }


    /**
     * Set item fields
     * @param  array $fields Array of fields
     * @return void
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
    }


    /**
     * Set field
     * @param string $name
     * @param mixed $value
     */
    public function setField(string $name, $value=null)
    {
        if (!(new Database())->setItemField($this->collectionId, $this->id, $name, $value)) {
            return false;
        }

        if (is_null($value)) {
            unset($fields[$name]);
        } else {
            // save field in item object
            $this->fields[$name] = $value;
        }

        return true;
    }


    /*
     *  Other methods
     */

    /**
     * Get parent collection object
     * @return object Collection object from Collection class
     */
    public function getCollection()
    {
        return Collection::getById($this->collectionId);
    }


    /**
     * Get item's owner ID
     * @return int User ID, FALSE if error
     */
    public function getOwner()
    {
        $collection = $this->getCollection();
        if (!$collection) {
            return false;
        }

        return $collection->getOwner();
    }


    /**
     * Dump item
     * @return array Item dumped
     */
    public function dump()
    {
        return [
            'id' => $this->id,
            'collectionId' => $this->collectionId,
            'fields' => $this->fields,
        ];
    }


    /**
     * Delete item
     * @return bool Success
     */
    public function delete()
    {
        return (new Database())->deleteItem($this->id);
    }


    /*
     *  Static methods
     */

    /**
     * Get item by ID
     * @param  int $id Item ID
     * @param  int $collectionId
     * @return object  Item object
     */
    public static function getById($id, $collectionId)
    {
        if (is_null($id)) {
            Logger::error("Called Item::getById(null)");
            return false;
        }

        $data = (new Database())->getItem($collectionId, $id);
        if (!$data) {
            return false;
        }

        $item = new self($collectionId, $data['id']);
        $item->setFields($data['fields']);

        return $item;
    }


    /**
    * Get item by property
    * @param  int $collectionId
    * @param  string $name
    * @param  mixed $value
    * @return object  Item object, false if not found
    */
    public static function getByProperty($collectionId, $name, $value)
    {
        if (is_null($name)) {
            Logger::error("Called Item::getByProperty(null)");
            return false;
        }

        $data = (new Database())->getItemByProperty($collectionId, $name, $value);
        if (!$data) {
            return false;
        }

        $item = new self($collectionId, $data['id']);
        $item->setFields($data['fields']);

        return $item;
    }
}
