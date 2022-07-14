<?php

abstract class GlobalImport
{
    protected $source;
    protected $fields;
    protected $options;
    protected $collection;
    protected $itemNameProperty;
    protected $importedProperties;
    protected $importedItemsCount;

    public function __construct($source, $options=[]) {
        $this->source = $source;
        $this->options = $options;

        $this->fields = [];
        $this->importedProperties = [];
        $this->importedItemsCount = 0;
    }

    /**
     * Import function, used for basic compatibility;
     * each child class should override this
     * @return array
     */
    public function import()
    {
        return false;
    }


    /**
     * Scan fields and save them
     * @return bool Success
     */
    public function setFields()
    {
        $fields = $this->scanFields();
        if ($fields === false) {
            return false;
        }

        $this->fields = $fields;
        return true;
    }


    /**
     * Save collection object to use it in import
     * @param  object $collection Collection object
     * @return void
     */
    public function setCollection(object $collection)
    {
        $this->collection = $collection;
        $this->itemNameProperty = $collection->getItemNameProperty();
    }


    /**
     * Return fields
     * (used for basic compatibility; each child class should override this)
     * @return array
     */
    public function scanFields()
    {
        return $this->fields;
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
     * @param  array  $properties
     * @return object Item object
     */
    protected function importItem(array $properties, $propertyId=null)
    {
        $item = false;

        if (!is_null($propertyId)) {
            if (isset($properties[$propertyId])) {
                // load existing item
                Logger::debug("Get item by property $propertyId=".$properties[$propertyId]);
                $item = Item::getByProperty($this->collection->getId(), $propertyId, $properties[$propertyId]);
            }
        }

        // create new item if not exists
        if (!$item) {
            $item = $this->collection->addItem();
            if (!$item) {
                Logger::error("failed to add item to collection ".$this->collection->getId());
                return false;
            }
        } else {
            Logger::debug("import: Item already exists: ID=".$item->getId());
        }

        // increment imported
        $this->importedItemsCount++;

        // load properties mapping if exists
        if (isset($this->options['mapping'])) {
            $mapping = $this->options['mapping'];
        } else {
            $mapping = [];
        }

        // get existing properties defined in collection
        $currentProperties = array_keys($this->collection->getProperties());

        // parse properties to insert
        foreach ($properties as $key => $values) {
            if (!is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $value) {
                // search for property mapping
                if (isset($mapping[$key])) {
                    // property mapping transform
                    if (isset($mapping[$key]['transform'])) {
                        if (!is_array($mapping[$key]['transform'])) {
                            $mapping[$key]['transform'] = [$mapping[$key]['transform']];
                        }

                        // do transformation(s)
                        foreach ($mapping[$key]['transform'] as $operation => $options) {
                            $value = $this->transform($value, $operation, $options);
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
                if (is_null($value)) {
                    continue;
                }

                // add property if not already imported
                if (in_array($key, $this->importedProperties) === false) {
                    // check if property is already defined in collection
                    if (in_array($key, $currentProperties)) {
                        $this->importedProperties[] = $key;
                    } else {
                        $propertyConfig = ['type' => 'text'];

                        // guess type of property from property name
                        switch ($key) {
                            case 'cover':
                                $propertyConfig['type'] = 'image';
                                $propertyConfig['isCover'] = true;
                                break;
                            case 'title':
                                $propertyConfig['isTitle'] = true;
                                break;
                            case 'image':
                            case 'rating':
                            case 'url':
                                $propertyConfig['type'] = $key;
                                break;
                        }

                        // add new property
                        if ($this->collection->addProperty($key, $propertyConfig)) {
                            $this->importedProperties[] = $key;
                        }
                    }
                }

                // set item property
                if ($key != '') {
                    $item->setProperty($key, $value);
                }
            }
        }

        return $item;
    }


    /**
     * Returns import report
     * @param  bool $result Success
     * @return array        Report
     */
    public function report($result)
    {
        return [
            'success'  => ($result ? true : false),
            'imported' => [
                'items'  => $this->importedItemsCount,
                'properties' => $this->importedProperties
            ]
        ];
    }
}
