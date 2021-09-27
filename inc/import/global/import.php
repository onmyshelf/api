<?php

class GlobalImport
{
    protected $source;
    protected $fields;
    protected $options;
    protected $collection;
    protected $itemNameField;
    protected $importedFields;
    protected $importedItemsCount;

    public function __construct($source, $options=[]) {
        $this->source = $source;
        $this->options = $options;

        $this->fields = [];
        $this->importedFields = [];
        $this->importedItemsCount = 0;
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
        $this->itemNameField = $collection->getItemNameField();
    }


    /**
     * Return fields (used for basic compatibility, to override)
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
    protected function transform($value, $operation = '')
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
                    Notification::notify("Failed to download url $url while import item field.", 'ERROR');
                    return null;
                }

                return $path;
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
     * @param  array  $fields
     * @return object Item object
     */
    protected function importItem(array $fields, $fieldId=null)
    {
        $item = false;

        if (!is_null($fieldId)) {
            if (isset($fields[$fieldId])) {
                // load existing item
                Logger::debug("Get item by property $fieldId=".$fields[$fieldId]);
                $item = Item::getByProperty($this->collection->getId(), $fieldId, $fields[$fieldId]);
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

        // load fields mapping if exists
        if (isset($this->options['mapping'])) {
            $mapping = $this->options['mapping'];
        } else {
            $mapping = [];
        }

        // get existing fields defined in collection
        $currentFields = array_keys($this->collection->getFields());

        // parse fields to insert
        foreach ($fields as $key => $values) {
            if (!is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $value) {
                // search for field mapping
                if (isset($mapping[$key])) {
                    // field mapping transform
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

                // if item name, set it
                if ($key == $this->itemNameField) {
                    $item->setName($value);
                }

                // add field if not already imported
                if (in_array($key, $this->importedFields) === false) {
                    // check if field is already defined in collection
                    if (in_array($key, $currentFields)) {
                        $this->importedFields[] = $key;
                    } else {
                        // guess type of field from field name
                        switch ($key) {
                            case 'id':
                            case 'image':
                            case 'rating':
                            case 'url':
                                $type = $key;
                                break;

                            default:
                                $type = 'text';
                                break;
                        }

                        // add new field
                        if ($this->collection->addField($key, ['type' => $type])) {
                            $this->importedFields[] = $key;
                        }
                    }
                }

                // set item field
                if ($key != '') {
                    $item->setField($key, $value);
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
            'success'  => $result,
            'imported' => [
                'items'  => $this->importedItemsCount,
                'fields' => $this->importedFields,
            ],
        ];
    }
}
