<?php

class Collection
{
    protected $id;
    protected $name;
    protected $description;
    protected $cover;
    protected $owner;
    protected $visibility;
    protected $fields;

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
     * Get fields
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }


    /*
     *  Other methods
     */



    /**
     * Return the field that is used for main item name
     * @return string Field name
     */
    public function getItemNameField()
    {
        foreach ($this->fields as $name => $field) {
            if ($field['isTitle']) {
                return $name;
            }
        }
        return false;
    }


    /**
     * Add item to collection
     * @return object Item
     */
    public function addItem()
    {
        $result = (new Database())->addItem($this->id);
        if (!$result) {
            return false;
        }

        $item = new Item($this->id, $result);
        return $item;
    }


    /**
     * Get collection items
     * @param  string $orderBy Option to order
     * @return array
     */
    public function getItems($orderBy=null)
    {
        return (new Database())->getItems($this->id, $orderBy);
    }


    /**
     * Add field to collection
     * @param  string  $name
     * @param  array   $properties
     * @return boolean Success
     */
    public function addField(string $name, array $properties=[])
    {
        // do nothing if already exists
        if (isset($this->fields[$name])) {
            return true;
        }

        $data = $properties;
        $data['name'] = $name;
        $data['collection'] = $this->id;

        if (!(new Database())->createField($this->id, $name, $data)) {
            return false;
        }

        $this->fields[$name] = $properties;
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
            'fields' => $this->fields
        ];
    }


    /**
     * Returns collection items
     * @param  array $filters  (optionnal)
     * @return array Collection dumped
     */
    public function dumpItems(array $filters=[])
    {
        // get items
        $result = $this->getItems();
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
                $itemFields = [];

                // parse fields of the item
                foreach ($dumpItem['fields'] as $key => $value) {
                    // search in collection definition of the field
                    if (isset($this->fields[$key])) {
                        if ($this->fields[$key]['isCover'] || $this->fields[$key]['isTitle'] || $this->fields[$key]['preview']) {
                            $itemFields[$key] = $value;
                        }
                    }

                    // filter item
                    if (isset($filters[$key])) {
                        $continue = ($value == $filters[$key]);
                    }
                }

                if ($continue) {
                    $items[] = [
                        'id' => $dumpItem['id'],
                        'fields' => $itemFields,
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
        $import->setFields();

        // run import
        $result = $import->import();

        // return report
        return $import->report($result);
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
        unset($allowed['fields']);
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
            if (!in_array($key, $data)) {
                Logger::debug("Failed to create collection; missing: $key");
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
     * Analyse fields for import
     * @param  string $type    Type of source
     * @param  string $source  Import source
     * @param  array  $options Options
     * @return array|bool      Array of fields, false if error
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

        // get and return fields
        return $import->scanFields();
    }
}
