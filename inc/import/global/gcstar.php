<?php

require_once('xml.php');

abstract class GCstarImport extends XmlImport
{
    protected $folder;


    /**
     * Open GCstar zipped file
     */
    public function load()
    {
        // extract zip file
        $this->folder = Storage::unzip($this->source, true);

        if (!$this->folder) {
            Logger::error("Failed to unzip GCstar archive.");
            return false;
        }

        // search gcs file
        $gcs = glob($this->folder.'/*.gcs');
        if (!$gcs) {
            Logger::error("Failed to find GCstar gcs file.");
            return false;
        }
        
        $this->source = $gcs[0];

        return parent::load();
    }


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

                // if image is defined,
                if (preg_match('/_pictures\//', $value)) {
                    $image = $this->folder.'/'.$value;
                    // if file exists, import it
                    if (file_exists($image)) {
                        $value = $this->importImage($image);
                    } else {
                        Logger::warn("GCstar import: failed to import image $image");
                    }
                }

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


    /**
     * Move image into media library
     *
     * @param string $path
     * @return string Media path
     */
    protected function importImage($path)
    {
        // move image to media library
        $path = Storage::move($path);
        if ($path) {
            return 'media://'.$path;
        } else {
            return false;
        }
    }
}
