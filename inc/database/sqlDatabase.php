<?php
/*
 *  Database support on standard SQL databases
 *  MySQL/MariaDB, PostgreSQL, SQLite should be classes that extends this one.
 */

abstract class SqlDatabase extends GlobalDatabase
{
    protected $connection;

    public function __construct()
    {
        try {
            $this->connection = new PDO(DATABASE.':host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASSWORD);
        } catch (Exception $e) {
            Logger::fatal("Error while connecting to database:\n".$e);
            exit(1);
        }
    }


    /**************
     *   MODELS   *
     **************/

    /*
     *  Authentication
     */

    /**
     * Create token for user
     * @param  string  $token
     * @param  integer $userId
     * @return boolean Success
     */
    public function createToken($token, $userId, $expiration=null, $ipOrigin=null, $type=null)
    {
        $args = [
            'token' => $token, 'userId' => $userId,
            'expiration' => $expiration,
            'created' => time(), 'ipIssuer' => $ipOrigin
        ];

        if (!is_null($type)) {
            $args['type'] = $type;
        }

        return $this->insertOne('token', $args);
    }


    /**
     * Delete token
     * @param  string  $token
     * @return boolean Success
     */
    public function deleteToken($token)
    {
        return $this->delete('token', ['token' => $token]);
    }


    /**
     * Delete all user's tokens
     * @param  int    $userId
     * @param  string $type (optional)
     * @return boolean Success
     */
    public function deleteUserTokens($userId, $type=null)
    {
        $filters = ['userId' => $userId];

        if (!is_null($type)) {
            $filters['type'] = $type;
        }

        return $this->delete('token', $filters);
    }


    /**
     * Clean expired tokens
     * @return bool Success
     */
    public function cleanupTokens()
    {
        return $this->write("DELETE FROM `token` WHERE `expiration`<=?", [time()]);
    }


    /*
     *  Config
     */

    /**
     * Dump all config values
     * @return array
     */
    public function dumpConfig()
    {
        // dump config values, except locked ones
        $db = $this->select("SELECT * FROM `config` WHERE `locked`=0");
        $config = [];

        foreach ($db as $row) {
            $param = $row['param'];
            $config[$param] = $row['value'];
        }

        return $config;
    }


    /**
     * Get config parameter value
     * @param  string $param Parameter name
     * @return mixed         Parameter value
     */
    public function getConfig($param)
    {
        return $this->selectOne("SELECT `value` FROM `config` WHERE `param`=?", [$param]);
    }


    /**
     * Set config parameter value
     * @param string $param   Parameter name
     * @param mixed  $value   Value
     * @param bool   $locked  Locked value
     * @return bool           Success
     */
    public function setConfig($param, $value, $locked=false)
    {
        // if locked, force change and set lock
        if (!$locked) {
            // prevent change if entry is locked
            if ($this->selectOne("SELECT `locked` FROM `config` WHERE `param`=?", [$param])) {
                return false;
            }
        }

        return $this->insertOne('config', ['param' => $param, 'value' => $value, 'locked' => ($locked ? 1 : 0)], ['value', 'locked']);
    }


    /*
     *  Collections
     */

    /**
     * Get all collections
     * @param  int     $owner      Show only owner (optional)
     * @param  boolean $isTemplate Get templates
     * @return array   Collections data
     */
    public function getCollections($owner=null, $isTemplate=false)
    {
        $query = "SELECT c.`id` FROM `collection` c JOIN `collectionLabel` l ON l.`collectionId`=c.`id`
                  WHERE `template`=?";
        $args = [$isTemplate];

        if (!is_null($owner)) {
            $query .= " AND `owner`=?";
            $args[] = $owner;
        }

        $query .= " GROUP BY `id` ORDER BY `name`";

        $ids = $this->selectColumn($query, $args);
        if ($ids === false) {
            return false;
        }

        $collections = [];
        foreach ($ids as $id) {
            $collection = $this->getCollection($id, $isTemplate);
            if (!$collection) {
                continue;
            }

            $accessRights = $GLOBALS['accessRights'];
            if ($collection['owner'] == $GLOBALS['currentUserID']) {
                $accessRights = 3;
            }

            if (!$isTemplate) {
                // get number of items
                $collection['items'] = $this->selectOne(
                    "SELECT COUNT(*) FROM `item` WHERE `collectionId`=? AND `visibility`<=?",
                    [$id, $accessRights]);
            }

            $collections[] = $collection;
        }

        return $collections;
    }


    /**
     * Get collection from ID
     * @param  int    $id Collection ID
     * @param  bool   $isTemplate Get a template
     * @return array  Collection data
     */
    public function getCollection($id, $isTemplate=false)
    {
        // get collection
        $collection = $this->selectFirst("SELECT * FROM `collection`
                                          WHERE `id`=? AND `template`=?",
                                         [$id, $isTemplate]);
        if (!$collection) {
            return false;
        }

        // security: filter
        if ($collection['visibility'] > $GLOBALS['accessRights'] &&
            $collection['owner'] != $GLOBALS['currentUserID']) {
            return false;
        }

        // clear unnecessary fields
        unset($collection['template']);

        // get collection labels
        $labels = $this->select("SELECT `lang`,`name`,`description`
                                 FROM `collectionLabel` WHERE `collectionId`=?",
                                [$id]);
        if ($labels === false)
            return false;

        $collection['name'] = [];
        $collection['description'] = [];

        foreach ($labels as $label) {
            $lang = $label['lang'];
            $collection['name'][$lang] = $label['name'];
            if ($label['description']) {
                $collection['description'][$lang] = $label['description'];
            }
        }

        $collection['tags'] = $this->getCollectionTags($id);

        // get collection properties
        $accessRights = $GLOBALS['accessRights'];
        if ($collection['owner'] == $GLOBALS['currentUserID']) {
            $accessRights = 3;
        }

        $results = $this->select("SELECT * FROM `property` WHERE `collectionId`=? AND `visibility`<=?
                                  ORDER BY `isCover` DESC,`isTitle` DESC,`isSubtitle` DESC,
                                  `preview` DESC,`order` DESC, `name`",
                                 [$id, $accessRights]);
        if (!$results) {
            $collection['properties'] = [];
            return $collection;
        }

        // get properties
        $properties = [];

        for ($i=0; $i < count($results); $i++) {
            $property = $results[$i];

            $property['label'] = [];
            $property['description'] = [];

            $labels = $this->select('SELECT `label`,`description`,`lang` FROM `propertyLabel`
                                     WHERE `propertyId`=?', [$property['id']]);
            if ($labels) {
                foreach ($labels as $label) {
                    $lang = $label['lang'];
                    $property['label'][$lang] = $label['label'];
                    $property['description'][$lang] = $label['description'];
                }
            }

            $name = $property['name'];

            // get available values
            if ($property['filterable']) {
                $property['values'] = $this->selectColumn('SELECT `value` FROM `itemProperty` WHERE `collectionId`=? AND `name`=?
                                               GROUP BY `value` ORDER BY `value`', [$id, $name]);
            }

            // delete unecessary data
            unset($property['id']);
            unset($property['collectionId']);
            unset($property['name']);

            // map name as array key
            $properties[$name] = $property;
        }

        $collection['properties'] = $properties;

        return $collection;
    }


    /**
     * Set updated field in collection table
     *
     * @param  int  $collectionId
     * @return bool Success
     */
    public function setCollectionUpdated($collectionId)
    {
        return $this->write("UPDATE `collection` SET `updated`=CURRENT_TIMESTAMP WHERE `id`=$collectionId");
    }


    /**
     * Transform label and description translation objects to arrays for database
     * @param  string $labelName
     * @param  array  $label
     * @param  array  $description
     * @return array
     */
    protected function labelToDB(string $labelName, $label, $description=[])
    {
        // get all langs
        $langs = array_unique(
            array_merge(array_keys($label), array_keys($description)),
            SORT_REGULAR
        );
        $result = [];

        // parse langs
        foreach ($langs as $lang) {
            $row = ['lang' => $lang];
            if (isset($label[$lang])) {
                $row[$labelName] = $label[$lang];
            }
            if (isset($description[$lang])) {
                $row['description'] = $description[$lang];
            }

            $result[] = $row;
        }

        return $result;
    }


    /**
     * Get collection tags
     *
     * @param int $collectionId
     * @return array
     */
    public function getCollectionTags($collectionId)
    {
        return $this->selectColumn("SELECT `tag` FROM `collectionTag` WHERE `collectionId`=?", [$collectionId]);
    }


    /**
     * Set collection tags
     *
     * @param int   $collectionId
     * @param array $tags
     * @return void
     */
    public function setCollectionTags($collectionId, $tags)
    {
        // prepare data
        $data = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag != '') {
                $data[] = ['collectionId' => $collectionId, 'tag' => $tag];
            }
        }

        // remove old tags
        $this->delete('collectionTag', ['collectionId' => $collectionId]);

        // insert tags
        if (count($data) > 0) {
            if (!$this->insert('collectionTag', $data)) {
                Logger::var_dump($rows);
            }
        }
    }


    /**
     * Create collection
     * @param  array $data
     * @return int   Created collection ID
     */
    public function createCollection($data)
    {
        // filter data keys because labels are not in the same table
        $row = $data;
        unset($row['name']);
        unset($row['description']);
        unset($row['properties']);
        unset($row['tags']);

        // set default owner if not specified
        if (!isset($row['owner'])) {
            $row['owner'] = 1;
        }

        // insert collection in table
        $id = $this->insertOne('collection', $row);
        if (!$id) {
            return false;
        }

        Logger::debug("Collection $id created");

        // label and description
        if (!isset($data['name'])) {
            // avoid bug
            $data['name'] = [];
        }
        if (!isset($data['description'])) {
            // avoid bug
            $data['description'] = [];
        }

        $rows = $this->labelToDB('name', $data['name'], $data['description']);
        foreach ($rows as &$row) {
            $row['collectionId'] = $id;
        }

        if (!$this->insert('collectionLabel', $rows)) {
            Logger::var_dump($rows);
        }

        // create properties if defined
        if (isset($data['properties'])) {
            foreach ($data['properties'] as $name => $params) {
                $this->setProperty($id, $name, $params);
            }
        }

        // create tags if defined
        if (isset($data['tags'])) {
            $this->setCollectionTags($id, $data['tags']);
        }

        return $id;
    }


    /**
     * Update collection
     * @param  int     $id   Collection ID
     * @param  array   $data
     * @return boolean Success
     */
    public function updateCollection($id, $data)
    {
        // filter data keys because labels are not in the same table
        $row = $data;
        unset($row['name']);
        unset($row['description']);
        unset($row['properties']);
        unset($row['tags']);

        // update collection table
        if (count($row) > 0) {
            if (!$this->update('collection', $row, ['id' => $id])) {
                return false;
            }
        }

        // label and description
        if (!isset($data['name'])) {
            // avoid bug
            $data['name'] = [];
        }
        if (!isset($data['description'])) {
            // avoid bug
            $data['description'] = [];
        }

        $rows = $this->labelToDB('name', $data['name'], $data['description']);
        if (count($rows) == 0) {
            return true;
        }

        foreach ($rows as &$row) {
            $row['collectionId'] = $id;
        }

        $orUpdate = [];
        if (count($data['name']) > 0) {
            $orUpdate[] = 'name';
        }
        if (count($data['description']) > 0) {
            $orUpdate[] = 'description';
        }

        // insert or update translations
        if (!$this->insert('collectionLabel', $rows, $orUpdate)) {
            Logger::var_dump($rows);
        }

        // update properties if defined
        if (isset($data['properties'])) {
            foreach ($data['properties'] as $name => $params) {
                $this->setProperty($id, $name, $params);
            }
        }

        // update tags if defined
        if (isset($data['tags'])) {
            $this->setCollectionTags($id, $data['tags']);
        }

        return true;
    }


    /**
     * Delete collection
     * @param  int  $id Collection ID
     * @return bool Success
     */
    public function deleteCollection($id)
    {
        // get associated items
        $items = $this->getItems($id);
        if ($items === false) {
            return false;
        }

        // delete items
        foreach ($items as $item) {
            $this->deleteItem($item);
        }

        // get associated properties
        $properties = $this->selectColumn("SELECT `name` FROM `property` WHERE `collectionId`=?", [$id]);
        if ($properties === false) {
            return false;
        }

        // delete properties
        foreach ($properties as $property) {
            $this->deleteProperty($id, $property);
        }

        // delete tags
        $this->delete('collectionTag', ['collectionId' => $id]);

        // delete collection labels
        $this->delete('collectionLabel', ['collectionId' => $id]);

        // delete collection
        return $this->delete('collection', ['id' => $id]);
    }


    /*
     *  Items
     */

    /**
     * Get items of a collection
     * @param  int   $collectionId Collection ID
     * @param  array $sortBy       (optionnal)
     * @return array|bool         Array of items IDs, FALSE if error
     */
    public function getItems($collectionId, $sortBy=[])
    {
        // no sorting => simple request on item table
        $query = "SELECT `id` FROM `item` WHERE `collectionId`=? AND `visibility`<=? ";
        $args = [$collectionId, $GLOBALS['accessRights']];

        // if sorting...
        if (count($sortBy) > 0) {
            // NOTE: this only works with one sorting field
            //       must be rewritten for multiple sorting fields
            $sorting = $sortBy[0];
            $order = ""; // default is ascending

            // descending order (e.g. -property)
            if (substr($sorting, 0, 1) == '-') {
                $sorting = substr($sorting, 1);
                $order = "DESC";
            }

            // the case of created/updated properties: are stored into item table
            if ($sorting == 'created' || $sorting == 'updated') {
                $query .= "ORDER BY `$sorting` $order, `name`";
            } else {
                // order by item property then by item name
                $query = "SELECT i.id, p.name, p.value FROM `item` i
                          LEFT JOIN `itemProperty` p ON p.itemId=i.id AND p.name=?
                          WHERE i.collectionId=? AND i.visibility<=?
                          ORDER BY p.value $order, i.name";

                // add property name to query args (first in array)
                array_unshift($args, $sorting);
            }
        } else {
            // or else: always sort by name
            $query .= "ORDER BY `name`";
        }

        return $this->selectColumn($query, $args);
    }


    /**
     * Get item by ID
     * @param  int  $collectionId
     * @param  int  $id   Item ID
     * @return array|bool Item data, FALSE if error
     */
    public function getItem($collectionId, $id)
    {
        $item = $this->selectFirst("SELECT * FROM `item` WHERE `collectionId`=? AND `id`=? AND `visibility`<=?",
                                   [$collectionId, $id, $GLOBALS['accessRights']]);
        if (!$item) {
            return false;
        }

        $properties = $this->getItemProperties($collectionId, $id);
        if (!$properties) {
            $item['properties'] = [];
        } else {
            $item['properties'] = $properties;
        }

        return $item;
    }


    /**
     * Get item properties
     * @param  int    $collectionId
     * @param  int    $itemId
     * @return array  SELECT results
     */
    public function getItemProperties($collectionId, $itemId)
    {
        $result = $this->select("SELECT i.name AS `name`, i.value AS `value` FROM `itemProperty` i
                                 JOIN `property` p ON i.collectionId=p.collectionId AND i.name=p.name
                                 WHERE i.collectionId=? AND i.itemId=? AND p.visibility<=?
                                 ORDER BY `name`, `value`",
                                [$collectionId, $itemId, $GLOBALS['accessRights']]);
        if ($result === false) {
            return false;
        }

        // transform ['name' => 'propertyName', 'value' => Value] to 'propertyName' => 'Value'
        $properties = [];
        $name = '';
        foreach ($result as $property) {
            // same name than before: group property to array
            if ($property['name'] == $name) {
                if (is_array($properties[$name])) {
                    $properties[$name][] = $property['value'];
                } else {
                    $properties[$name] = [$properties[$name], $property['value']];
                }

                $name = $property['name'];
            } else {
                $name = $property['name'];
                $properties[$name] = $property['value'];
            }
        }

        return $properties;
    }


    /**
     * Get item property
     * @param  int    $collectionId
     * @param  int    $itemId
     * @param  string $name  Property name
     * @return array  SELECT results
     */
    public function getItemProperty($collectionId, $itemId, $name)
    {
        $properties = $this->selectColumn('SELECT i.value AS `value` FROM `itemProperty` i
                                       JOIN `property` p ON i.collectionId=p.collection AND i.name=p.name
                                       WHERE i.collectionId=? AND i.itemId=? AND p.name=? AND p.visibility<=?
                                       ORDER BY `name`',
                                      [$collectionId, $itemId, $name, $GLOBALS['accessRights']]);
        if (!$properties) {
            return false;
        }

        if (count($properties) == 0) {
            return null;
        } elseif (count($properties) == 1) {
            return $properties[0];
        }

        return $properties;
    }


    /**
     * Get item ID by property
     * @param  int    $collectionId
     * @param  string $name         Property name
     * @param  mixed  $value        Property value
     * @return array|bool Item data, FALSE if error
     */
    public function getItemByProperty($collectionId, $name, $value)
    {
        $itemId = $this->selectOne("SELECT `itemId` FROM `itemProperty` WHERE `collectionId`=? AND `name`=? AND `value`=?",
                                   [$collectionId, $name, $value]);
        if (!$itemId) {
            return false;
        }

        return $this->getItem($collectionId, $itemId);
    }


    /**
     * Set updated field in item table
     *
     * @param  int  $collectionId
     * @param  int  $itemId
     * @return bool Success
     */
    public function setItemUpdated($collectionId, $itemId)
    {
        return $this->write("UPDATE `item` SET `updated`=CURRENT_TIMESTAMP WHERE `collectionId`=$collectionId AND `id`=$itemId");
    }


    /**
     * Creates an item
     * @param  int   $collectionId
     * @param  array $data Item data (optionnal)
     * @return bool Success
     */
    public function createItem($collectionId, $data=[])
    {
        // append collection ID
        $data['collectionId'] = $collectionId;

        // insert item & get its ID
        $id = $this->insertOne('item', $data);
        if ($id) {
            // notify collection has changed (ignore errors)
            $this->setCollectionUpdated($collectionId);
        }

        return $id;
    }


    /**
     * Update item
     * @param  int     $collectionId Collection ID
     * @param  int     $id           Item ID
     * @param  array   $data
     * @return boolean Success
     */
    public function updateItem($collectionId, $id, $data)
    {
        $updatedProperties = false;
        $itemTitle = null;

        if (isset($data['properties'])) {
            // get title property
            $titleProperty = $this->selectOne("SELECT `name` FROM `property` WHERE `collectionId`=$collectionId AND `isTitle`=1");
            
            $properties = $data['properties'];

            foreach ($properties as $property => $value) {
                // update property
                if ($this->setItemProperty($collectionId, $id, $property, $value)) {
                    // if item title, change it
                    if ($property == $titleProperty) {
                        $itemTitle = $value;
                    }
                    $updatedProperties = true;
                } else {
                    Logger::warn('Failed to set item property '.$property.' for item '.$id.
                                 ' in collection '.$collectionId);
                }
            }

            unset($data['properties']);
        }

        // notify item has changed (ignore errors)
        if ($updatedProperties) {
            $this->setItemUpdated($collectionId, $id);
        }

        // change item title if needed (limit characters)
        if (isset($itemTitle)) {
            if (is_array($itemTitle)) {
                $itemTitle = $itemTitle[0];
            }
            $data['name'] = substr($itemTitle, 0, 250);
        }

        // if nothing left to update, quit
        if (count($data) == 0) {
            return true;
        }

        // update item
        return $this->update('item', $data, ['collectionId' => $collectionId, 'id' => $id]);
    }


    /**
     * Rename all items of a collection, based on the property title
     *
     * @param int $collectionId
     * @return void
     */
    protected function renameItems(int $collectionId)
    {
        // get title property of the collection
        $titleProperty = $this->selectOne("SELECT `name` FROM `property` WHERE `collectionId`=$collectionId AND `isTitle`=1");

        // get all items of the collection
        $items = $this->selectColumn("SELECT `id` FROM `item` WHERE `collectionId`=$collectionId");
        foreach ($items as $itemId) {
            // set item name from the title property (limited)
            if (!$this->write("UPDATE `item`
                               SET `name`=(SELECT SUBSTRING(`value`, 1, 200) FROM `itemProperty` WHERE `collectionId`=? AND `itemId`=? AND `name`=?)
                               WHERE `id`=?", [$collectionId, $itemId, $titleProperty, $itemId])) {
                Logger::error("Failed to change item name for item $itemId of collection $collectionId");
            }
        }
    }


    /**
     * Delete item
     * @param  int $id Item ID
     * @return bool    Success
     */
    public function deleteItem($id)
    {
        // delete item loans
        if (!$this->delete('loan', ['itemId' => $id])) {
            return false;
        }
        
        // delete item properties
        if (!$this->delete('itemProperty', ['itemId' => $id])) {
            return false;
        }

        // delete item
        return $this->delete('item', ['id' => $id]);
    }


    /**
     * Set item property
     * @param int $itemId  Item ID
     * @param string $name Property name
     * @param mixed $value Value
     * @return bool        Success
     */
    public function setItemProperty($collectionId, $itemId, $name, $value)
    {
        // delete old entries
        if (!$this->delete('itemProperty', ['collectionId' => $collectionId, 'itemId' => $itemId, 'name' => $name])) {
            return false;
        }

        // always convert value to array
        if (!is_array($value)) {
            $value = [$value];
        }

        $values = [];
        foreach ($value as $v) {
            // value null or empty: do nothing
            if ($v === null || $v === '') {
                continue;
            }

            // false value: convert to 0 to avoid empty values
            if ($v === false) {
                $v = 0;
            }

            $values[] = $v;
        }

        // no values: do nothing
        if (count($values) == 0) {
            return true;
        }

        $data = [];
        $entry = [
            'collectionId' => $collectionId,
            'itemId' => $itemId,
            'name' => $name,
        ];

        foreach ($values as $v) {
            $data[] = array_merge($entry, ['value' => $v]);
        }

        return ($this->insert('itemProperty', $data));
    }


    /*
     *  Loans
     */


    /**
     * Get item loans
     *
     * @param int $itemId
     * @return void
     */
    public function getItemLoans($itemId)
    {
        return $this->select("SELECT * FROM `loan` WHERE `itemId`=? ORDER BY `lent` DESC", [$itemId]);
    }


    /**
     * Get loan by ID
     * @param  int  $id   Loan ID
     * @return array|bool Item data, FALSE if error
     */
    public function getLoan($id)
    {
        return $this->selectFirst("SELECT * FROM `loan` WHERE `id`=?", [$id]);
    }


    /**
     * Get if item is lent
     *
     * @param int $itemId
     * @return boolean
     */
    public function isItemLent($itemId)
    {
        return $this->selectOne("SELECT COUNT(*) FROM `loan` WHERE `itemId`=? AND `state`='lent'
                                 AND `lent` <= ?", [$itemId, time()]);
    }


    /**
     * Get item pending loans
     *
     * @param int $itemId
     * @return boolean
     */
    public function getItemPendingLoans($itemId)
    {
        return $this->selectOne("SELECT COUNT(*) FROM `loan` WHERE `itemId`=? AND `state`='accepted'", [$itemId]);
    }


    /**
     * Get item pending asks
     *
     * @param int $itemId
     * @return boolean
     */
    public function getItemAskedLoans($itemId)
    {
        return $this->selectOne("SELECT COUNT(*) FROM `loan` WHERE `itemId`=? AND `state`='asked'", [$itemId]);
    }


    /**
     * Creates loan
     * @param  int   $itemId
     * @param  array $data Item data (optionnal)
     * @return bool Success
     */
    public function createLoan($data)
    {
        return $this->insertOne('loan', $data);
    }


    /**
     * Update loan
     * @param  int     $id
     * @param  array   $data
     * @return boolean Success
     */
    public function updateLoan($id, $data)
    {
        if (count($data) == 0) {
            return true;
        }

        return $this->update('loan', $data, ['id' => $id]);
    }


    /**
     * Deletes a loan
     * @param  int   $id
     * @return bool Success
     */
    public function deleteLoan($id)
    {
        return $this->delete('loan', ['id' => $id]);
    }


    /*
     *  Borrowers
     */

    /**
     * Get borrowers
     * @param  array $data
     * @return bool Success
     */
    public function getBorrowers()
    {
        $query = "SELECT * FROM `borrower` WHERE `owner`=? OR `visibility`< 3 ORDER BY `firstname`, `lastname`";
        return $this->select($query, [$GLOBALS['currentUserID']]);
    }


    /**
     * Get borrower by id
     * @param  int  $id
     * @return bool Success
     */
    public function getBorrowerById($id)
    {
        $query = "SELECT * FROM `borrower` WHERE `id`=? AND (`owner`=? OR `visibility`< 3)";
        return $this->selectFirst($query, [$id, $GLOBALS['currentUserID']]);
    }

    
    /**
     * Creates borrower
     * @param  array $data
     * @return bool Success
     */
    public function createBorrower($data)
    {
        return $this->insertOne('borrower', $data);
    }


    /**
     * Update borrower
     * @param  int     $id
     * @param  array   $data
     * @return boolean Success
     */
    public function updateBorrower($id, $data)
    {
        if (count($data) == 0) {
            return true;
        }

        return $this->update('borrower', $data, ['id' => $id]);
    }


    public function deleteBorrower($id)
    {
        return $this->delete('borrower', ['id' => $id]);
    }


    /*
     *  Properties
     */

    /**
     * Get collection property definition
     * @param  int    $collectionId Collection ID
     * @param  string $name         Property name
     * @return array                Array of data
     */
    public function getProperty($collectionId, $name)
    {
        // get properties
        $property = $this->selectFirst("SELECT * FROM `property` WHERE `collectionId`=? AND `name`=?",
                                    [$collectionId, $name]);
        if (!$property) {
            return false;
        }

        $property['label'] = [];
        $property['description'] = [];

        $labels = $this->select("SELECT `label`,`description`,`lang` FROM `propertyLabel`
                                 WHERE `propertyId`=?", [$property['id']]);
        if ($labels) {
            foreach ($labels as $label) {
                $lang = $label['lang'];
                $property['label'][$lang] = $label['label'];
                $property['description'][$lang] = $label['description'];
            }
        }

        return $property;
    }


    /**
     * Check property parameters
     *
     * @param int    $collectionId
     * @param string $name
     * @param array  $params
     * @return void
     */
    protected function checkPropertyParams($collectionId, $name, $params)
    {
        // fields that needs to be unique
        $fields = [
            'isId',
            'isTitle',
            'isSubTitle',
            'isCover',
        ];

        foreach ($fields as $field) {
            // reset if needed
            if (isset($params[$field]) && $params[$field]) {
                // force all other properties to have this field = false
                if (!$this->write("UPDATE `property` SET `$field`=? WHERE `collectionId`=? AND NOT `name`=?", [0, $collectionId, $name])) {
                    Logger::error("Failed to update property fields");
                }

                // if property title has changed, rename all items
                if ($field == 'isTitle') {
                    $this->renameItems($collectionId);
                }
            }
        }
    }


    /**
     * Set property (insert or update)
     * @param  int    $collectionId
     * @param  string $name  Property name
     * @param  array  $data
     * @return bool   Success
     */
    public function setProperty($collectionId, $name, $data)
    {
        // filter data keys because labels are not in the same table
        $row = $data;
        unset($row['label']);
        unset($row['description']);
        // remove values if herited from a collection
        unset($row['values']);

        // convert booleans
        foreach ($row as &$param) {
            if ($param === true) {
                $param = 1;
            } elseif ($param === false) {
                $param = 0;
            }
        }
        unset($param); // PHP recommendation

        // creates property if not exists
        if (!$this->exists('property', ['collectionId' => $collectionId, 'name' => $name])) {
            $row['collectionId'] = $collectionId;
            $row['name'] = $name;

            if ($this->insertOne('property', $row) === false) {
                Logger::error("Failed to create new property '$name' in collection $collectionId");
                return false;
            }
        } else {
            // or update property
            if (count($row) > 0) {
                if (!$this->update('property', $row, ['collectionId' => $collectionId, 'name' => $name])) {
                    Logger::debug("Failed to update property '$name' in collection $collectionId");
                    return false;
                }
            }
        }

        // check parameters
        $this->checkPropertyParams($collectionId, $name, $row);

        // label and description
        if (!isset($data['label'])) {
            // avoid bug
            $data['label'] = [];
        }
        if (!isset($data['description'])) {
            // avoid bug
            $data['description'] = [];
        }

        $rows = $this->labelToDB('label', $data['label'], $data['description']);
        if (count($rows) == 0) {
            return true;
        }

        // get property ID
        $propertyId = $this->selectOne("SELECT `id` FROM `property` WHERE `collectionId`=? AND `name`=?",
            [$collectionId, $name]
        );
        if (!$propertyId) {
            Logger::error("Failed to get property ID for collection $collectionId, property: $name");
            return false;
        }

        foreach ($rows as &$row) {
            $row['propertyId'] = $propertyId;
        }

        $orUpdate = [];
        if (count($data['label']) > 0) {
            $orUpdate[] = 'label';
        }
        if (count($data['description']) > 0) {
            $orUpdate[] = 'description';
        }

        // insert or update translations
        if (!$this->insert('propertyLabel', $rows, $orUpdate)) {
            Logger::var_dump($rows);
        }

        return true;
    }


    /**
     * Delete property
     * @param  int $id Property ID
     * @return bool    Success
     */
    public function deleteProperty($collectionId, $name)
    {
        // delete item values of this property
        if (!$this->delete('itemProperty', ['collectionId' => $collectionId, 'name' => $name])) {
            return false;
        }

        // get property ID
        $id = $this->selectOne('SELECT `id` FROM `property` WHERE `collectionId`=? AND `name`=?',
                               [$collectionId, $name]);
        if (!$id) {
            return false;
        }

        // delete property labels
        if (!$this->delete('propertyLabel', ['propertyId' => $id])) {
            return false;
        }

        // delete property
        return $this->delete('property', ['id' => $id]);
    }


    /*
     *  Notifications
     */

    /**
     * Insert notification
     * @param string $userId
     * @param string $type
     * @param string $text
     * @return bool  Success
     */
    public function addNotification($userId, $type, $text)
    {
        return $this->insertOne('notification', ['userId' => $userId, 'type' => $type, 'text' => $text]);
    }


    /*
     *  Storage
     */

    /**
     * Search if media is used
     * @param  string $path
     * @return boolean
     */
    public function mediaExists($path)
    {
        // search in collection covers
        $count = $this->count('collection', ['cover' => $path]);
        // if error, consider test as true to avoid delete real used medias
        if ($count === false || $count > 0) {
            return true;
        }

        // search in config
        $count = $this->count('config', ['value' => $path]);
        if ($count === false || $count > 0) {
            return true;
        }

        // search in items
        $count = $this->count('itemProperty', ['value' => $path]);
        if ($count === false || $count > 0) {
            return true;
        }

        // search in user avatars
        $count = $this->count('user', ['avatar' => $path]);
        if ($count === false || $count > 0) {
            return true;
        }

        return false;
    }


    /*
     *  Users
     */

    /**
     * Get users
     * @return array
     */
    public function getUsers()
    {
        return $this->select("SELECT `id`,`username`,`firstname`,`lastname`,`enabled`,`email`,`avatar` FROM `user`");
    }


    /**
     * Get user by id
     * @param  string $id
     * @return array  Result
     */
    public function getUserById($id)
    {
        return $this->selectFirst("SELECT * FROM `user` WHERE `id`=?", [$id]);
    }


    /**
     * Get user by login
     * @param  string $login Username or email
     * @return array Result
     */
    public function getUserByLogin($login)
    {
        return $this->selectFirst("SELECT * FROM `user` WHERE (`username`=? OR `email`=?)", [$login, $login]);
    }


    /**
     * Get user by token
     * @param  string $token
     * @return array  Result
     */
    public function getUserByToken($token, $type=null)
    {
        $query = "SELECT u.* FROM `user` u JOIN `token` t ON u.`id`=t.`userId`
                  AND t.`token`=? AND t.`expiration`>?";
        $args = [$token, time()];

        if (is_null($type)) {
            // search for all standard tokens
            $query .= " AND t.`type`<>'resetpassword'";
        } else {
            // search for a particular token type
            $query .= " AND t.`type`=?";
            $args[] = $type;
        }

        return $this->selectFirst($query, $args);
    }


    /**
     * Create user
     * @param  array $data
     * @return bool   Success
     */
    public function createUser($data)
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        // convert boolean
        if (isset($data['enabled'])) {
            if ($data['enabled']) {
                $data['enabled'] = 1;
            } else {
                $data['enabled'] = 0;
            }
        }

        return $this->insertOne('user', $data);
    }


    /**
     * Update user profile
     * @param  int    $id
     * @param  array  $data
     * @return bool   Success
     */
    public function updateUser($id, $data)
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        // convert boolean
        if (isset($data['enabled'])) {
            if ($data['enabled']) {
                $data['enabled'] = 1;
            } else {
                $data['enabled'] = 0;
            }
        }

        return $this->update('user', $data, ['id' => $id]);
    }


    /**
     * Unlink user from borrower
     * @param int   $userId
     * @return bool Success
     */
    public function unlinkUserBorrower($userId)
    {
        return $this->update('borrower', ['userId' => null], ['userId' => $userId]);
    }


    /**
     * Delete user
     * @param  int    $id
     * @return bool   Success
     */
    public function deleteUser($id)
    {
        return $this->delete('user', ['id' => $id]);
    }


    /**
     * Initialize database structure
     * @return bool  Success
     */
    public function install()
    {
        // loads SQL file
        $sql = file_get_contents(__DIR__.'/init/'.DATABASE.'.sql');

        // allow multiple queries
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        if (!$this->execute($sql)) {
            Logger::fatal('Database initialization failed!');
            return false;
        }

        return parent::install();
    }


    /**
     * Upgrade database
     * @param  string Version to upgrade to
     * @return bool   Success
     */
    public function upgrade($newVersion)
    {
        // get current version
        $currentVersion = $this->getConfig('version');

        echo "Create missing tables...\n";
        if (!$this->install()) {
            Logger::fatal("Failed to init tables");
            return false;
        }

        // call custom upgrade functions
        $changes = [
            "1.1.0" => "upgrade_v110",
            "1.3.0" => "upgrade_v130",
            "1.3.2" => "upgrade_v132",
            "1.4.0" => "upgrade_v140",
        ];

        // migrate versions step-by-step
        foreach ($changes as $version => $function) {
            // if current version is lower than changes version, run custom upgrade function
            if (Config::compareVersions($version, $currentVersion)) {
                // if migration method exists, run it
                if (method_exists($this, $function)) {
                    echo "Migrate database from '$currentVersion' to '$version'...\n";
                    if ($this->$function() !== false) {
                        // set upgraded version into database
                        parent::upgrade($version);
                    } else {
                        return false;
                    }
                }
            }
        }

        // set upgraded version into database
        return parent::upgrade($newVersion);
    }


    /**
     * Initialize database content
     * @return bool   Success
     */
    public function init()
    {
        $models = glob(__DIR__.'/init/collectionTemplates/*.json');
        foreach ($models as $model) {
            Logger::debug("Initialize collection template from: $model");

            // initialize default collection templates from JSON file
            $json = file_get_contents($model);
            if (!$json) {
                Logger::error("Failed to load collection templates from JSON definition!");
                return false;
            }

            // open json
            $templates = json_decode($json, true);
            foreach ($templates as $template) {
                // merge properties
                foreach ($template['properties'] as $name => $property) {
                    $template['properties'][$name] = array_merge(Property::guessConfigFromName($name), $property);
                }

                // get template ID (if exists)
                $templateId = $this->selectOne("SELECT `id` FROM `collection` WHERE `template`=1 AND `type`=?",
                                               [$template['type']]);

                if ($templateId) {
                    $this->updateCollectionTemplate($templateId, $template);
                } else {
                    $this->createCollectionTemplate($template);
                }
            }
        }

        // init default config
        $config = [
            'loans' => 1,
        ];
        foreach ($config as $param => $value) {
            if ($this->count('config', ['param' => $param]) == 0) {
                if (!$this->setConfig($param, $value)) {
                    Logger::error("Failed to initialize default config for $param");
                }
            }
        }

        return true;
    }


    /************************
     *  DATABASE SQL UTILS  *
     ************************/

    /**
     * Runs a SQL query (e.g. ALTER TABLE)
     *
     * @param string $sql SQL query
     * @return bool  Success
     */
    protected function execute(string $sql)
    {
        return $this->connection->exec($sql) !== false;
    }


    /**
     * Runs a SELECT query
     * @param string $query SQL query
     * @param array $args
     * @return array|bool
     */
    protected function select(string $query, array $args=[])
    {
        $stmt = $this->connection->prepare($query);
        if ($stmt === false) {
            Logger::error("Error while create statement for query: $query");
            return false;
        }

        // Logger::debug("SQL query: $query");
        // Logger::var_dump($args);

        if ($stmt->execute($args) === false) {
            return false;
        }

        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;
    }


    /**
     * Creates a prepared query, binds the given parameters and returns the result of the executed
     * @param string $query SQL query
     * @param array $args   Array key => value
     * @return bool
     */
    protected function write(string $query, array $args=[])
    {
        if (count($args) == 0) {
            return false;
        }

        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            Logger::error("Error while create statement for query: $query");
            return false;
        }

        // Logger::debug("SQL query: $query");
        // Logger::var_dump($args);

        return $stmt->execute($args);
    }


    /**
     * Runs a SELECT query and returns the first row of results
     * @param  string $query SQL SELECT query
     * @param  array  $args  Array of args
     * @return array|bool    Array of results, FALSE if error
     */
    protected function selectFirst($query, array $args=[])
    {
        // runs select method
        $result = $this->select($query, $args);

        // returns first row of results
        if ($result) {
            return $result[0];
        } else {
            return false;
        }
    }


    /**
     * Runs a SELECT query and returns the first column in the first row of results
     * @param  string $query SQL SELECT query
     * @param  array  $args  Array of args
     * @return array|bool    Array of results, FALSE if error
     */
    protected function selectOne($query, array $args=[])
    {
        // runs select method
        $result = $this->selectFirst($query, $args);

        // returns first column of results
        if ($result) {
            return array_values($result)[0];
        } else {
            return false;
        }
    }


    /**
     * Runs a SELECT query and returns the results of the desired column
     * @param  string $query SQL SELECT query
     * @param  array  $args  Array of args
     * @return array|bool    Array of results, FALSE if error
     */
    protected function selectColumn($query, array $args=[], $column=0)
    {
        // runs select method
        $result = $this->select($query, $args);

        // returns first column of results
        if ($result === false) {
            return false;
        }

        $values = [];

        foreach ($result as $value) {
            $values[] = array_values($value)[$column];
        }

        return $values;
    }


    /**
     * Runs SELECT COUNT query
     * @param  string $table   Table name
     * @param  array  $filters Array of filters
     * @return array|bool  Array of results, FALSE if error
     */
    protected function count($table, array $filters=[])
    {
        $query = "SELECT COUNT(*) FROM `$table`";

        if (count($filters) > 0) {
            $query .= " WHERE ";

            foreach ($filters as $key => $value) {
                $query .= "`$key`=? AND ";
            }

            // delete last " AND "
            $query = substr($query, 0, -5);
        }

        // runs query
        return $this->selectOne($query, array_values($filters));
    }


    /**
     * Check if a value exists
     * @param  string $table
     * @param  array  $filters
     * @return bool   Exists
     */
    protected function exists(string $table, array $filters=[])
    {
        $count = $this->count($table, $filters);
        if (!is_integer($count)) {
            return false;
        }

        return $count > 0;
    }


    /**
     * Insert one entry
     * @param  string $table Table name
     * @param  array  $args  Array key => value
     * @param  array  $orUpdate
     * @return bool
     */
    protected function insertOne(string $table, array $values, array $orUpdate=[])
    {
        return $this->insert($table, [$values], $orUpdate);
    }


    /**
     * Runs UPDATE query
     * @param  string $table   Table name
     * @param  array  $values  Array of values
     * @param  array  $filters Array of filters
     *  @return bool            Success
     */
    protected function update(string $table, array $values=[], array $filters=[])
    {
        if (count($values) == 0) {
            return false;
        }

        $query = 'UPDATE `'.$table.'` SET ';

        foreach ($values as $key => $v) {
            $query .= '`'.$key.'`=:'.$key.',';
        }
        // delete last ","
        $query = substr($query, 0, -1);

        if (count($filters) > 0) {
            $query .= ' WHERE ';

            foreach ($filters as $key => $v) {
                $query .= '`'.$key.'`=:'.$key.' AND ';
            }

            // delete last " AND "
            $query = substr($query, 0, -5);
        }

        $args = array_merge($values, $filters);

        return $this->write($query, $args);
    }


    /**
     * Runs DELETE query
     * @param  string $table   Table name
     * @param  array  $filters Array of filters
     * @return bool   Success
     */
    protected function delete(string $table, array $filters=[])
    {
        $query = 'DELETE FROM '.$table;

        if (count($filters) > 0) {
            $query .= ' WHERE ';

            foreach ($filters as $key => $value) {
                $query .= '`'.$key.'`=:'.$key.' AND ';
            }

            // delete last " AND "
            $query = substr($query, 0, -5);
        }

        return $this->write($query, $filters);
    }
}
