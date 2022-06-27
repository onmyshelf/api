<?php
require_once('inc/import/global/xml.php');

class Import extends XmlImport
{
    /**
     * Scan fields of the collection
     * @return bool  Success
     */
    public function scanFields()
    {
        $fields = [];

        // get attributes
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

        return true;
    }
}
