<?php

class Collection
{
    protected $id;
    protected $name;
    protected $description;
    protected $type;
    protected $cover;
    protected $owner;
    protected $visibility;
    protected $borrowable;
    protected $created;
    protected $updated;
    protected $properties;
    protected $tags;


    public function __construct($data=null)
    {
        // affect object properties from $data
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


    /**
     * Get borrowable level
     * @return array
     */
    public function getBorrowableLevel()
    {
        // check if loans are disabled
        if (!Config::get('loans')) {
            return 4;
        }

        return $this->borrowable;
    }


    /*
     *  Other methods
     */

    /**
     * Return the property that is used for main item name
     * @return string Property name
     */
    public function getItemIdProperty()
    {
        foreach ($this->properties as $name => $property) {
            if ($property['isId']) {
                return $name;
            }
        }
        return false;
    }


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
        return Item::create($this->id, $data);
    }


    /**
     * Get collection items
     * @param  array $sortBy  Fields to sort items
     * @return array
     */
    public function getItems($sortBy=[])
    {
        return (new Database)->getItems($this->id, $sortBy);
    }


    /**
     * Add property to collection
     * @param  string  $name
     * @param  array   $params
     * @return boolean Success
     */
    public function addProperty($name, array $params=[])
    {
        // do nothing if already exists
        if (isset($this->properties[$name])) {
            return true;
        }

        if (!(new Database)->setProperty($this->id, $name, $params)) {
            return false;
        }

        $this->properties[$name] = $params;
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
            'thumbnails' => Storage::getThumbnails($this->cover),
            'owner' => $this->owner,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'borrowable' => $this->borrowable,
            'created' => $this->created,
            'updated' => $this->updated,
            'properties' => $this->properties,
            'tags' => $this->tags,
        ];
    }


    /**
     * Returns collection items
     * @param  array  $filters (optionnal)
     * @param  array  $sortBy  (optionnal)
     * @param  string $search  (optionnal)
     * @param  int    $limit   (optionnal)
     * @param  int    $offset  (optionnal)
     * @return array  Collection dumped
     */
    public function dumpItems($filters=[], $sortBy=[], $search='', $limit=0, $offset=0)
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
            $result = [];
        }

        $items = [];

        // parse items
        $i = 0;
        foreach ($result as $itemId) {
            $item = Item::getById($itemId, $this->id);
            if (!$item) {
                continue;
            }

            // dump item
            $dumpItem = $item->dump();

            $itemProperties = [];
            $thumbnails = [];

            $continue = true;
            foreach ($filters as $filter_key => $filter_value) {
                // if value not defined in this object, ignore it
                if (!isset($dumpItem['properties']) || !isset($dumpItem['properties'][$filter_key])) {
                    $continue = false;
                    break;
                }

                $value = $dumpItem['properties'][$filter_key];

                // if property has multiple values,
                if (is_array($value)) {
                    // filter each value
                    $found_value = false;
                    foreach ($value as $v) {
                        // if one value is found, it's OK
                        if ($this->filterProperty($filter_key, $v, $filter_value)) {
                            $found_value = true;
                            break;
                        }
                    }
                    if (!$found_value) {
                        $continue = false;
                        break;
                    }
                } else {
                    // single value
                    if (!$this->filterProperty($filter_key, $value, $filter_value)) {
                        $continue = false;
                        break;
                    }
                }
            }

            // if filters not passed, ignore and go to the next item
            if (!$continue) {
                continue;
            }

            // search
            if ($search) {
                $continue = false;
                // parse properties of the item
                foreach ($dumpItem['properties'] as $key => $value) {
                    // search in collection definition of the property
                    if (isset($this->properties[$key])) {
                        if (!$this->properties[$key]['searchable']) {
                            continue;
                        }
                        // if property has multiple values,
                        if (is_array($value)) {
                            // filter each value
                            $found_value = false;
                            foreach ($value as $v) {
                                // if one value is found, it's OK
                                if (stripos($v, $search) !== false) {
                                    $found_value = true;
                                    break;
                                }
                            }
                            if ($found_value) {
                                $continue = true;
                                break;
                            }
                        } else {
                            // single value
                            if (stripos($value, $search) !== false) {
                                $continue = true;
                                break;
                            }
                        }
                    }
                }
                // if search not found, ignore and go to the next item
                if (!$continue) {
                    continue;
                }
            }

            if ($limit > 0) {
                if ($i < $offset || $i >= $limit + $offset) {
                    $i++;
                    continue;
                }
            }

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
            }

            // append item to dump
            $items[] = [
                'id' => $dumpItem['id'],
                'properties' => $itemProperties,
                'thumbnails' => $thumbnails,
                'quantity' => $dumpItem['quantity'],
                'visibility' => $dumpItem['visibility'],
                'borrowable' => $dumpItem['borrowable'],
                'lent' => $item->isLent(),
                'pendingLoans' => $item->getPendingLoans(),
                'askingLoans' => $item->getAskingLoans(),
            ];

            $i++;
        }

        return [
            'total' => $i,
            'items' => $items,
        ];
    }


    /**
     * Export collection
     * @return array Collection dumped
     */
    public function export()
    {
        $result = $this->dump();

        // get items
        $items = $this->getItems();
        if (!$items) {
            $items = [];
        }

        $result['items'] = [];

        // parse items
        foreach ($items as $itemId) {
            $item = Item::getById($itemId, $this->id);
            if (!$item) {
                continue;
            }

            $itemDump = $item->dump();

            // get loans
            $itemDump['loans'] = $item->getLoans();

            // dump item
            $result['items'][] = $itemDump;
        }

        return $result;
    }


    /**
     * Filter item by property
     *
     * @param str $name     Name of property
     * @param mixed $value  Value of the property
     * @param str $filter   Value to filter
     * @return Bool
     */
    protected function filterProperty($name, $value, $filter) : Bool
    {
        switch ($this->properties[$name]['type']) {
            case 'yesno':
                # convert to boolean
                return (filter_var($value, FILTER_VALIDATE_BOOLEAN) == filter_var($filter, FILTER_VALIDATE_BOOLEAN));
                break;

            case 'number':
            case 'rating':
                # property of type number: filter by value or boundaries
                if (preg_match('/^>.+/', $filter)) {
                    # syntax >min
                    return $value >= substr($filter, 1);
                } elseif (preg_match('/^<.+/', $filter)) {
                    # syntax <max
                    return $value <= substr($filter, 1);
                } elseif (preg_match('/^.+<.+/', $filter)) {
                    # syntax min<max
                    $boundaries = explode('<', $filter);
                    return ($value >= $boundaries[0] && $value <= $boundaries[1]);
                } else {
                    # simple value
                    return $value == $filter;
                }
                break;
            
            default:
                return strtolower($value) == strtolower($filter);
                break;
        }
    }


    /**
     * Import collection
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array           Import report
     */
    public function import($module, $source, $options=[])
    {
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
     * Get data from import module
     * @param  string $module
     * @param  string $source
     * @param  array  $options
     * @return array  Data
     */
    public function importGetData($module, $source, $options=[])
    {
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
        
        return $import->getData();
    }


    /**
     * Import item
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array           Import report
     */
    public function importItem($module, $source, $options=[])
    {
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
        if (!$import->importItem($this, $import->getData())) {
            return false;
        }

        return $import->report();
    }


    /**
     * Search for import
     * @param  string $module
     * @param  string $source
     * @param  string $search
     * @param  array  $options
     * @return array           Import report
     */
    public function importSearch($module, $source, $search, $options=[])
    {
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
        
        return $import->search($search);
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
        unset($allowed['created']);
        unset($allowed['updated']);
        $allowed = array_keys($allowed);

        foreach (array_keys($data) as $key) {
            // remove non allowed data
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
                continue;
            }
            
            // check values
            switch ($key) {
                case 'borrowable':
                case 'visibility':
                    if (!Visibility::validateLevel($data[$key])) {
                        Logger::debug("Update collection error: bad $key: ".$data[$key]);
                        return false;
                    }
                    break;
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
        return (new Database)->deleteCollection($this->id);
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
        return (new Database)->getCollections($userID);
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

        $data = (new Database)->getCollection($id);
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

        // defines allowed data fields
        $allowed = [
            'name',
            'description',
            'type',
            'cover',
            'owner',
            'visibility',
            'borrowable',
            'properties',
            'tags',
        ];

        foreach (array_keys($data) as $key) {
            // remove non allowed data
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
                continue;
            }

            // check values
            switch ($key) {
                case 'borrowable':
                case 'visibility':
                    if (!Visibility::validateLevel($data[$key])) {
                        Logger::debug("Create collection error: bad $key: ".$data[$key]);
                        return false;
                    }
                    break;
            }
        }

        // create in database
        return (new Database)->createCollection($data);
    }
}