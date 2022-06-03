<?php

class Item
{
    protected $id;
    protected $collectionId;
    protected $name;
    protected $properties;
    protected $visibility;

    public function __construct($data=null)
    {
        // affect properties from $data
        foreach (array_keys(get_object_vars($this)) as $p) {
            if (isset($data[$p])) {
                $this->$p = $data[$p];
            }
        }
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
     * Get property value(s)
     * @param  string $name Property name
     * @return mixed        Value or array of values
     */
    public function getProperty($name)
    {
        return (new Database())->getItemProperty($this->collectionId, $this->id, $name);
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
     * Set property
     * @param string $name
     * @param mixed $value
     */
    public function setProperty(string $name, $value=null)
    {
        if (!(new Database())->setItemProperty($this->collectionId, $this->id, $name, $value)) {
            return false;
        }

        if (is_null($value)) {
            unset($this->properties[$name]);
        } else {
            // save property in item object
            $this->properties[$name] = $value;
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
            'properties' => $this->properties,
            'visibility' => $this->visibility
        ];
    }


    /**
     * Update item data
     * @param  array   $data
     * @return boolean
     */
    public function update($data)
    {
        // remove non allowed data
        $allowed = get_object_vars($this);
        unset($allowed['id']);
        unset($allowed['collectionId']);
        unset($allowed['name']);
        $allowed = array_keys($allowed);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        return (new Database)->updateItem($this->collectionId, $this->id, $data);
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

        return new self($data);
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

        return new self($data);
    }


    /**
     * Get data
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array|bool      Array of properties, false if error
     */
    public static function importData($type, $source, $options=[])
    {
        require_once('inc/classes/Module.php');
        if (!Module::load('import', $type)) {
            return false;
        }

        try {
            $import = new Import($source, $options);
        } catch (Throwable $t) {
            Logger::fatal("error while loading import class: $type");
            return false;
        }

        // get fields
        $import->setFields();

        // get data
        return $import->getData();
    }
}
