<?php

class Field
{
    private $collectionId;
    private $name;
    private $label;
    private $description;
    private $type;
    private $suffix;
    private $default;
    private $visibility;
    private $required;
    private $hideLabel;
    private $isTitle;
    private $isSubTitle;
    private $isCover;
    private $preview;
    private $filterable;
    private $order;

    public function __construct($data=null)
    {
        // affect properties from $data
        foreach (array_keys(get_object_vars($this)) as $p) {
            if (isset($data[$p])) {
                $this->$p = $data[$p];
            }
        }
    }


    /**
     * Update field
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
        return (new Database)->updateField($this->collectionId, $this->name, $data);
    }


    /**
     * Delete field
     * @return bool
     */
    public function delete()
    {
        return (new Database)->deleteField($this->collectionId, $this->name);
    }


    /*
     *  Static functions
     */

    /**
     * Get collection field by name
     * @param  int    $collectionId
     * @param  string $name
     * @return object Field object
     */
    public static function getByName($collectionId, $name)
    {
        if (is_null($collectionId)) {
            Logger::error("Called Field::getByName(null,$name)");
            return false;
        }

        $data = (new Database())->getField($collectionId, $name);
        if (!$data) {
            return false;
        }

        return new self($data);
    }


    /**
     * Get field types
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
            'file'
        ];
    }
}
