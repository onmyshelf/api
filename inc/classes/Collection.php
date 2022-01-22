<?php

class Collection
{
    protected $id;
    protected $name;
    protected $description;
    protected $cover;
    protected $owner;
    protected $visibility;
    protected $properties;

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
     *  Getters
     */

    /**
     * Get collection ID
     * @return int Collection ID
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
     * Get properties
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }


    /*
     *  Other methods
     */



    /**
     * Return the property that is used for main item name
     * @return string Property name
     */
    public function getItemNameProperty()
    {
        foreach ($this->properties as $name => $property) {
            if ($property['isTitle']) {
                return $name;
            }
        }
        return false;
    }


    /**
     * Add item to collection
     * @return object Item
     */
    public function addItem($data=[])
    {
        $properties = [];

        if (isset($data['properties'])) {
            $properties = $data['properties'];
            unset($data['properties']);
        }

        $id = (new Database())->createItem($this->id, $data);
        if (!$id) {
            Logger::error("Failed to create item");
            return false;
        }

        $data['id'] = $id;
        $data['collectionId'] = $this->id;
        $data['properties'] = $properties;
        $item = new Item($data);

        foreach ($properties as $key => $value) {
            if (!$item->setProperty($key, $value)) {
                Logger::error("Failed to set property $key to item $id");
            }
        }

        return $item;
    }


    /**
     * Get collection items
     * @param  array $sortBy  Fields to sort items
     * @return array
     */
    public function getItems($sortBy=[])
    {
        return (new Database())->getItems($this->id, $sortBy);
    }


    /**
     * Add property to collection
     * @param  string  $name
     * @param  array   $properties
     * @return boolean Success
     */
    public function addProperty(string $name, array $properties=[])
    {
        // do nothing if already exists
        if (isset($this->properties[$name])) {
            return true;
        }

        if (!(new Database())->createProperty($this->id, $name, $properties)) {
            return false;
        }

        $this->properties[$name] = $properties;
        return true;
    }


    /**
     * Returns collection object data
     * @param  array $filters  (optionnal)
     * @return array Collection dumped
     */
    public function dump(array $filters=[])
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cover' => $this->cover,
            'owner' => $this->owner,
            'visibility' => $this->visibility,
            'properties' => $this->properties
        ];
    }


    /**
     * Returns collection items
     * @param  array $filters (optionnal)
     * @param  array $sortBy  (optionnal)
     * @return array Collection dumped
     */
    public function dumpItems($filters=[], $sortBy=[])
    {
        // get items
        $result = $this->getItems($sortBy);
        if (!$result) {
            return [];
        }

        $items = [];

        // parse items
        foreach ($result as $i) {
            $item = Item::getById($i, $this->id);
            if ($item) {
                $dumpItem = $item->dump();

                $continue = count($filters) == 0;
                $filterFound = false;
                $itemProperties = [];

                // parse properties of the item
                foreach ($dumpItem['properties'] as $key => $value) {
                    // search in collection definition of the property
                    if (isset($this->properties[$key])) {
                        if ($this->properties[$key]['isCover'] || $this->properties[$key]['isTitle'] || $this->properties[$key]['preview']) {
                            $itemProperties[$key] = $value;
                        }
                    }

                    // filter item
                    if (isset($filters[$key])) {
                        if (is_array($value)) {
                            if (in_array($filters[$key], $value)) {
                                $continue = true;
                            }
                        } else {
                            if ($value == $filters[$key]) {
                                $continue = true;
                            }
                        }
                    }
                }

                if ($continue) {
                    $items[] = [
                        'id' => $dumpItem['id'],
                        'properties' => $itemProperties,
                    ];
                }
            }
        }

        return $items;
    }


    /**
     * Import data
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array           Import report
     */
    public function import($type, $source, $options=[])
    {
        if (!self::importInit($type)) {
            return false;
        }

        try {
            $import = new Import($source, $options);
        } catch (Throwable $t) {
            Logger::fatal("error while loading import class: $type");
            return false;
        }

        $import->setCollection($this);
        $import->setProperties();

        // return report
        return $import->report($import->import());
    }


    /**
     * Update collection data
     * @param  array   $data
     * @return boolean
     */
    public function update($data)
    {
        // remove non allowed data
        $allowed = get_object_vars($this);
        unset($allowed['id']);
        unset($allowed['properties']);
        $allowed = array_keys($allowed);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        return (new Database)->updateCollection($this->id, $data);
    }


    /**
     * Delete collection
     * @return bool Success
     */
    public function delete()
    {
        return (new Database())->deleteCollection($this->id);
    }


    /********************
    *  STATIC METHODS  *
    ********************/

    /**
     * Dump all collections
     * @param  int   $userID (optional)
     * @return array Array of collections
     */
    public static function dumpAll($userID=null)
    {
        return (new Database())->getCollections($userID);
    }


    /**
     * Get collection object by ID
     * @param  int $id Collection ID
     * @return object  Collection object
     */
    public static function getById($id)
    {
        if (is_null($id)) {
            Logger::error("Called Collection::getById(null)");
            return false;
        }

        $data = (new Database())->getCollection($id);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Create collection
     * @param  array $data
     * @return int   Collection ID, FALSE if error
     */
    public static function create($data)
    {
        // check required data
        $required = ['name'];
        foreach ($required as $key) {
            if (!isset($data[$key])) {
                Logger::error("Failed to create collection; missing: $key");
                return false;
            }
        }

        // remove non allowed data
        $allowed = [
            'name', 'description',
            'cover', 'owner', 'visibility'
        ];
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        // create in database
        return (new Database)->createCollection($data);
    }


    /*
     *  Import methods
     */

    /**
     * Initialize import classes
     * @param  string $type Type of import
     * @return bool         Success
     */
    private static function importInit(string $type)
    {
        // check type (security)
        if (!preg_match('/^\w+$/', $type)) {
            Logger::error("bad import type: $type");
            return false;
        }

        // load main class
        require_once('inc/import/global/import.php');

        // load module if exists
        if (file_exists("inc/modules/import/$type/import.php")) {
            try {
                require_once("inc/modules/import/$type/import.php");
                return true;
            } catch (Throwable $t) {
                Logger::fatal("error while loading import module: $type");
                return false;
            }
        }

        // load internal class if exists
        if (file_exists("inc/import/$type.php")) {
            require_once("inc/import/$type.php");
            return true;
        }

        Logger::error("unknown import type: $type");
        return false;
    }


    /**
     * Analyse properties for import
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array|bool      Array of properties, false if error
     */
    public static function scanImport($type, $source, $options=[])
    {
        if (!self::importInit($type)) {
            return false;
        }

        try {
            $import = new Import($source, $options);
        } catch (Throwable $t) {
            Logger::fatal("error while loading import class: $type");
            return false;
        }

        // get and return properties
        return $import->scanProperties();
    }
}
