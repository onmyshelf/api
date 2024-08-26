<?php

abstract class GlobalImport
{
    protected $source;
    protected $options;
    protected $properties;
    protected $importedProperties;
    protected $importedItems;
    protected $importErrors;


    public function __construct($source, $options = [])
    {
        $this->source = $source;
        $this->options = $options;

        $this->properties = [];
        $this->importedProperties = [];
        $this->importedItems = [];
        $this->importErrors = [];
    }


    /**
     * Load source (e.g. open file, set properties)
     *
     * @return boolean Success
     */
    abstract public function load();


    /**
     * Search for items.
     * Override this method if you want to use it.
     *
     * @param string $text Text to search
     * @return array Array of results
     */
    public function search($text) 
    {
        return false;
    }


    /**
     * Get item data.
     * It is meant to be used with $this->source as item data source.
     * You should override this method, unless you have rewritten the import() method.
     *
     * @return array  Array of properties and values
     */
    public function getData()
    {
        return false;
    }


    /**
     * Return properties
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }


    /**
     * Import items, here just one item, but you can override this
     * @return array
     */
    public function import($collection)
    {
        return $this->importItem($collection, $this->getData());
    }


    /**
     * Transform a field
     * @param  mixed $value      Value to transform
     * @param  string $operation Transform operation
     * @return mixed             Value transformed
     */
    protected function transform($value, $operation='', $options=[])
    {
        switch ($operation) {
            case 'delete':
                return null;
                break;

            case 'download':
                $url = (string)$value;
                if (!preg_match('/:\/\//', $url) && substr($url, 0, 1) != '/') {
                    $url = MEDIA_DIR.'/upload/'.$url;
                }

                $path = Storage::copy($url);
                if (!$path) {
                    Notification::notify("Failed to download url $url while import item property.", 'ERROR');
                    return null;
                }

                return $path;
                break;

            case 'replace':
                if (isset($options['regex']) && isset($options['replace'])) {
                    try {
                        // return replacement
                        return preg_replace($options['regex'], $options['replace'], $value);
                    } catch (Throwable $t) {
                        Logger::warning("error in import replace transform");
                    }
                }
                // return with no changes
                return $value;
                break;

            case 'toString':
                if (is_array($value)) {
                    return implode(', ', array_filter($value));
                } else {
                    return (string)$value;
                }
                break;

            default:
                return $value;
                break;
        }
    }


    /**
     * Import item in database
     * @param  object $collection
     * @param  array  $data
     * @param  string $propertyId  Property to be used as id to avoid duplicates
     * @return object Item object
     */
    public function importItem($collection, $data, $propertyId=null)
    {
        $item = false;

        if (!$propertyId) {
            $propertyId = $collection->getItemIdProperty();
        }
        if ($propertyId) {
            if (isset($data[$propertyId])) {
                // load existing item
                Logger::debug("Get item by property $propertyId=".$data[$propertyId]);
                $item = Item::getByProperty($collection->getId(), $propertyId, $data[$propertyId]);
            }
        }

        // create new item if not exists
        if (!$item) {
            $item = $collection->addItem();
            if (!$item) {
                Logger::error("failed to add item to collection ".$collection->getId());
                return false;
            }
        } else {
            Logger::debug("import: Item already exists: ID=".$item->getId());
        }

        // increment imported
        $this->importedItems[] = $item->getId();

        // load properties mapping if exists
        if (isset($this->options['mapping'])) {
            $mapping = $this->options['mapping'];
        } else {
            $mapping = [];
        }

        // get existing properties defined in collection
        $currentProperties = array_keys($collection->getProperties());

        $importedItemProperties = [];

        // parse properties to insert
        foreach ($data as $key => $values) {
            if (!is_array($values)) {
                $values = [$values];
            }

            $value = [];
            foreach ($values as $v) {
                // search for property mapping
                if (isset($mapping[$key])) {
                    // property mapping transform
                    if (isset($mapping[$key]['transform'])) {
                        if (!is_array($mapping[$key]['transform'])) {
                            $mapping[$key]['transform'] = [$mapping[$key]['transform']];
                        }

                        // do transformation(s)
                        foreach ($mapping[$key]['transform'] as $operation => $options) {
                            $v = $this->transform($v, $operation, $options);
                        }
                    }

                    if (isset($mapping[$key]['field'])) {
                        $key = $mapping[$key]['field'];

                        // empty: do not import value
                        if ($key == '') {
                            continue;
                        }
                    }
                }

                // if value is null, ignore it
                if (is_null($v)) {
                    continue;
                }

                // trim value
                if (is_string($v)) {
                    $v = trim($v);
                    // if value is an empty string, ignore it
                    if ($v == '') {
                        continue;
                    }
                }

                // create property if not already imported
                if (in_array($key, $this->importedProperties) === false) {
                    // check if property is already defined in collection
                    if (in_array($key, $currentProperties)) {
                        $this->importedProperties[] = $key;
                    } else {
                        // get property definition from import module
                        if (isset($this->properties[$key])) {
                            $propertyConfig = $this->properties[$key];
                        } else {
                            $propertyConfig = [];
                        }

                        // try to guess property config from its name
                        $propertyConfig = array_merge(Property::guessConfigFromName($key), $propertyConfig);

                        // add new property
                        if ($collection->addProperty($key, $propertyConfig)) {
                            $this->importedProperties[] = $key;
                        }
                    }
                }

                // add property value
                if ($key != '') {
                    $value[] = $v;
                }
            }

            // save property to import
            $importedItemProperties[$key] = $value;
        }

        // update item if there are properties to import
        if (count($importedItemProperties) > 0) {
            $item->update(["properties" => $importedItemProperties]);
        }

        return $item;
    }


    /**
     * Returns import report
     * @param  bool $result Success
     * @return array        Report
     */
    public function report()
    {
        return [
            'imported' => [
                'items'  => $this->importedItems,
                'properties' => $this->importedProperties,
            ],
            'errors' => $this->importErrors,
        ];
    }


    public function cleanup()
    {
        // if source is a file, delete it
        Storage::delete($this->source);
    }


    /**
     * Download a file into the media library
     *
     * @param  string $url           URL of file
     * @param  bool   $ignore_errors Keep URL if could not be downloaded
     * @return string Media URL, FALSE if error
     */
    protected function download($url, $ignore_errors = false)
    {
        $path = Storage::download($url);
        if ($path) {
            return $path;
        }

        // if failed,
        if ($ignore_errors) {
            Logger::debug("Failed to download for import (ignored): $url");
            return $url;
        } else {
            Logger::error("Failed to download for import: $url");
            return false;
        }
    }
}
