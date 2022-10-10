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
     * @return array Collection dumped
     */
    public function dump()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cover' => $this->cover,
            'thumbnail' => Storage::getThumbnails($this->cover),
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
        // default sorting by main name property
        if (count($sortBy) == 0) {
            $nameProperty = $this->getItemNameProperty();
            if ($nameProperty) {
                $sortBy[] = $nameProperty;
            }
        }

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
                $thumbnails = [];

                // parse properties of the item
                foreach ($dumpItem['properties'] as $key => $value) {
                    // search in collection definition of the property
                    if (isset($this->properties[$key])) {
                        if ($this->properties[$key]['isCover'] || $this->properties[$key]['isTitle'] || $this->properties[$key]['preview']) {
                            $itemProperties[$key] = $value;
                        }
                        // add thumbnails
                        if ($this->properties[$key]['isCover']) {
                            $thumbnails = Storage::getThumbnails($value);
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
                        'thumbnail' => $thumbnails,
                        'visibility' => $dumpItem['visibility'],
                        'lent' => $item->isLent(),
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
    public function import($module, $source, $options=[])
    {
        require_once('inc/classes/Module.php');
        if (!Module::load('import', $module)) {
            return false;
        }

        try {
            $import = new Import($source, $options);
        } catch (Throwable $t) {
            Logger::fatal("error while loading import class: $module");
            return false;
        }

        if (!$import->load()) {
            return false;
        }
        if (!$import->import($this)) {
            return false;
        }

        return $import->report();
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
     * Analyse fields for import
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array|bool      Array of properties, false if error
     */
    public static function scanImport($module, $source, $options=[])
    {
        require_once('inc/classes/Module.php');
        if (!Module::load('import', $module)) {
            return false;
        }

        try {
            $import = new Import($source, $options);
        } catch (Throwable $t) {
            Logger::fatal("error while loading import class: $module");
            return false;
        }

        // get and return properties
        return $import->getProperties();
    }
}