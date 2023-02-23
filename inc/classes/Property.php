<?php

class Property
{
    private $collectionId;
    private $name;
    private $label;
    private $description;
    private $type;
    private $suffix;
    private $default;
    private $authorizedValues;
    private $visibility;
    private $required;
    private $hideLabel;
    private $isId;
    private $isTitle;
    private $isSubTitle;
    private $isCover;
    private $preview;
    private $filterable;
    private $searchable;
    private $sortable;
    private $order;


    public function __construct($params=null)
    {
        foreach (array_keys(get_object_vars($this)) as $p) {
            if (isset($params[$p])) {
                $this->$p = $params[$p];
            }
        }
    }


    /**
     * Update property
     * @param  array $data
     * @return bool
     */
    public function update($data)
    {
        // remove non allowed data
        $allowed = get_object_vars($this);
        unset($allowed['collection']);
        unset($allowed['name']);
        $allowed = array_keys($allowed);
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed)) {
                unset($data[$key]);
            }
        }

        // create in database
        return (new Database)->updateProperty($this->collectionId, $this->name, $data);
    }


    /**
     * Delete property
     * @return bool
     */
    public function delete()
    {
        return (new Database)->deleteProperty($this->collectionId, $this->name);
    }


    /*
     *  Static functions
     */

    /**
     * Get property by name
     * @param  int    $collectionId
     * @param  string $name
     * @return object Property object
     */
    public static function getByName($collectionId, $name)
    {
        if (is_null($collectionId)) {
            Logger::error("Called Property::getByName(null,$name)");
            return false;
        }

        $data = (new Database)->getProperty($collectionId, $name);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Get property types
     * @return array
     */
    public static function getTypes()
    {
        return [
            'text',
            'image',
            'number',
            'date',
            'rating',
            'yesno',
            'longtext',
            'datetime',
            'url',
            'file',
            'color',
        ];
    }
}
