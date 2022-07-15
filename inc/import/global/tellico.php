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
    public function __construct($file, $options=[])
    {
        // extract tc file
        $folder = Storage::unzip($file, true);

        if (!$folder) {
            Logger::error("Failed to unzip Tellico file.");
            return false;
        }

        parent::__construct($folder.'/tellico.xml', $options);
    }

    
    /**
     * Scan fields of the collection
     * @return bool  Success
     */
    public function scanFields()
    {
        if (!property_exists($this->xml, 'collection') || !isset($this->xml->collection)) {
            Logger::warn("No fields found in Tellico XML!");
            return [];
        }

        // get attributes
        $fields = [];
        foreach ($this->xml->collection->fields->field as $field) {
            $name = (string) $field->attributes()['name'];
            // avoid duplicates
            if (!in_array($name, $fields)) {
                $fields[] = $name;
            }
        }

        return $fields;
    }


    /**
     * Import data into collection
     * @return bool Import success
     */
    public function import()
    {
        // parse items
        foreach ($this->xml->collection->entry as $item) {
            $fields = [];

            // get id attribute
            $fields['id'] = $this->xml->collection->entry->attributes()['id'];

            // get fields
            foreach ($item as $key => $values) {
                $transform = 'toString';

                // array of values
                if ($values->children()->count()) {
                    // custom fields
                    switch ($key) {
                        case 'cdate':
                        case 'mdate':
                            // convert dates
                            $value = $values->year.'-'.$values->month.'-'.$values->day;
                            break;

                        default:
                            // parse subvalues
                            foreach ($values as $v) {
                                // store field
                                $value = $this->transform($v, $transform);
                            }
                            break;
                    }
                } else {
                    // single field
                    $value = $this->transform($values, $transform);
                }

                $fields[$key] = $value;
            }

            // import item
            $this->importItem($fields, 'id');
        }

        $this->cleanup();

        return true;
    }
}
