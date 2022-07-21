<?php

require_once('xml.php');

abstract class GCstarImport extends XmlImport
{
    /**
     * Scan fields of the GCstar file
     * @return array Array of fields names
     */
    public function getProperties()
    {
        $this->properties = [];

        // scan first item to get fields

        // get attributes
        foreach ($this->xml->item[0]->attributes() as $key => $value) {
            // avoid duplicates
            if (!in_array($key, $this->properties)) {
                $this->properties[] = $key;
            }
        }

        // get other fields
        foreach ($this->xml->item[0] as $key => $value) {
            // avoid duplicates
            if (! in_array($key, $this->properties)) {
                $this->properties[] = $key;
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
        foreach ($this->xml->item as $item) {
            $fields = [];

            // get attributes
            foreach ($item->attributes() as $key => $value) {
                $transform = 'toString';

                // custom fields
                switch ($key) {
                    case 'added':
                    case 'date':
                        // if only a year, treat as an integer
                        if (strlen($value) == 4) {
                            $value = (int)$value;
                        } else {
                            // convert French date to universal
                            $date = DateTime::createFromFormat('d/m/Y', (string)$value);
                            if ($date) {
                                $value = $date->format('Y-m-d');
                            }
                        }
                        break;

                    case 'borrower':
                        // ignore no borrower
                        $value = (string)$value;
                        if ($value == 'none') {
                            $value = '';
                        }
                        break;

                    case 'favourite':
                    case 'identifier':
                        $value = (string)$value;
                        if ($value == '0') {
                            $value = '';
                        }
                        break;

                    case 'rating':
                    case 'ratingpress':
                        if ($value == 0) {
                            // ignore 0
                            $value = '';
                        } else {
                            // transform rating /10 -> /5
                            $value = (int)$value / 2;
                        }
                        break;
                }

                $value = $this->transform($value, $transform);

                // add field if not empty
                if (strlen($value)) {
                    $fields[$key] = $value;
                }
            }

            // get other fields (using groups)
            foreach ($item as $key => $values) {
                $transform = 'toString';

                $values = (array)$values;

                foreach ($values as $value) {
                    $value = (array)$value;

                    foreach ($value as $val) {
                        if (isset($val->col)) {
                            $val = $val->col;
                        }

                        $final = $this->transform((array)$val, $transform);

                        if (isset($fields[$key])) {
                            $fields[$key][] = $final;
                        } else {
                            $fields[$key] = [$final];
                        }
                    }
                }
            }

            // import item
            $this->importItem($collection, $fields, 'id');
        }

        $this->cleanup();

        return true;
    }
}
