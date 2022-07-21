<?php

require_once('inc/import/global/xml.php');

abstract class TellicoImport extends XmlImport
{
    protected $folder;

    /**
     * Class constructor
     * @param string $file    The path to the Tellico file to import
     * @param array  $options Import options
     */
    public function load()
    {
        // extract tc file
        $this->folder = Storage::unzip($this->source, true);

        if (!$this->folder) {
            Logger::error("Failed to unzip Tellico file.");
            return false;
        }

        $this->source = $this->folder.'/tellico.xml';

        return parent::load();
    }


    /**
     * Scan fields of the collection
     * @return bool  Success
     */
    public function getProperties()
    {
        $this->properties = [];

        if (!property_exists($this->xml, 'collection') || !isset($this->xml->collection)) {
            Logger::warn("No fields found in Tellico XML!");
            return [];
        }

        // get attributes
        foreach ($this->xml->collection->fields->field as $field) {
            $name = (string) $field->attributes()['name'];
            // avoid duplicates
            if (!in_array($name, $this->properties)) {
                $this->properties[] = $name;
            }
        }

        return $this->properties;
    }


    /**
     * Import data into collection
     * @return bool Import success
     */
    public function import($collection)
    {
        // parse items
        foreach ($this->xml->collection->entry as $item) {
            $fields = [];

            // get id attribute
            $fields['id'] = $this->xml->collection->entry->attributes()['id'][0];

            // get fields
            foreach ($item as $key => $values) {
                // array of values
                if ($values->children()->count()) {
                    $value = [];
                    foreach ($values as $v) {
                        $value[] = $this->importValue($key, $v);
                    }
                } else {
                    // single field
                    $value = $this->importValue($key, $values);
                }

                $fields[$key] = $value;
            }

            // import item
            $this->importItem($collection, $fields, 'id');
        }

        //$this->cleanup();

        return true;
    }


    protected function importValue($key, $value, $transform = 'toString')
    {
        switch ($key) {
            case 'cdate':
            case 'mdate':
                // convert dates
                $value = $value->year.'-'.$value->month.'-'.$value->day;
                break;

            case 'cover':
                $value = $this->importImage((string) $value);
                break;

            default:
                $value = $this->transform($value, $transform);
                break;
        }

        return $value;
    }

    
    protected function importImage($id)
    {
        // parse images
        foreach ($this->xml->collection->images->image as $image) {
            // check id attribute
            if ($image->attributes()['id'] == $id) {
                // move image to media library
                $path = Storage::move($this->folder.'/images/'.$id);
                if ($path) {
                    return 'media://'.$path;
                } else {
                    return false;
                }
            }
        }
    }
}
