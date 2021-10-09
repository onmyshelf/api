<?php
/*
 *  Database support on standard SQL databases
 *  MySQL/MariaDB, PostgreSQL, SQLite should be classes that extends this one.
 *
 *  DISCLAIMER:
 *  I know, NoSQL like mongoDB should be used for this project, but for now,
 *  I try to do my best with the things I know best.
 *  Please help me to make OnMyShelf better!
 */

class SqlDatabase extends GlobalDatabase
{
    /**************
     *   MODELS   *
     **************/

    /*
     *  Authentication
     */

    /**
     * Create token for user
     * @param  string  $token
     * @param  string  $username
     * @return boolean Success
     */
    public function createToken(string $token, string $username, $expiration=null, $ipOrigin=null)
    {
        // get user ID from username
        $userId = $this->selectOne("SELECT `id` FROM `user` WHERE `username`=?", [$username]);
        if (!is_integer($userId)) {
            return false;
        }

        return $this->insertOne('token', [
            'token' => $token, 'userId' => $userId, 'expiration' => $expiration,
            'created' => time(), 'ipIssuer' => $ipOrigin]);
    }


    /**
     * Check token and returns user ID
     * @param  string  $token
     * @return integer User ID, FALSE if not found
     */
    public function getUserFromToken(string $token)
    {
        return $this->selectFirst(
            "SELECT u.id AS `id`, u.username AS `username`
             FROM `token` t JOIN `user` u ON t.userId=u.id
             WHERE t.token=? AND t.expiration>?",
            [$token, time()]);
    }


    /**
     * Delete token
     * @param  string  $token
     * @return boolean Success
     */
    public function deleteToken(string $token)
    {
        return $this->delete('token', ['token' => $token]);
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
     * Get config parameter value
     * @param  string $param Parameter name
     * @return mixed         Parameter value
     */
    public function getConfig(string $param)
    {
        return $this->selectOne("SELECT `value` FROM `config` WHERE `param`=?", [$param]);
    }


    /**
     * Set config parameter value
     * @param string $param Parameter name
     * @param mixed $value  Value
     * @return bool         Success
     */
    public function setConfig(string $param, $value)
    {
        // insert or update value
        if ($this->getConfig($param)) {
            return $this->update('config',
                ['param' => $param], ['value' => $value]
            );
        } else {
            // TODO: insertOrUpdate method does not work on this case; don't know why
            return $this->insertOne('config',
                ['param' => $param, 'value' => $value]
            );
        }
    }


    /*
     *  Collections
     */

    /**
     * Get all collections
     * @param  int     $owner    Show only owner (optional)
     * @param  boolean $template Get templates
     * @return array             Collections data
     */
    public function getCollections($owner=null, $template=false)
    {
        $query = "SELECT `id` FROM `collection` WHERE `template`=?";
        $args = [$template];

        if (!is_null($owner)) {
            $query .= " AND `owner`=?";
            $args[] = $owner;
        }

        $ids = $this->selectColumn($query, $args);
        if ($ids === false) {
            return false;
        }

        $collections = [];
        foreach ($ids as $id) {
            $collection = $this->getCollection($id);
            if (!$collection) {
                continue;
            }

            $accessRights = $GLOBALS['accessRights'];
            if ($collection['owner'] == $GLOBALS['currentUserID']) {
                $accessRights = 3;
            }

            if (!$template) {
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
     * @param  bool   $template Get a template
     * @return array  Collection data
     */
    public function getCollection($id, $template=false)
    {
        // get collection
        $collection = $this->selectFirst("SELECT `id`,`cover`,`owner`,`visibility`
                                          FROM `collection` WHERE `id`=? AND `template`=?",
                                         [$id, $template]);
        if (!$collection) {
            return false;
        }

        // security: filter
        if ($collection['visibility'] > $GLOBALS['accessRights'] &&
            $collection['owner'] != $GLOBALS['currentUserID'])
            return false;

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

        // get collection fields
        $accessRights = $GLOBALS['accessRights'];
        if ($collection['owner'] == $GLOBALS['currentUserID']) {
            $accessRights = 3;
        }

        $results = $this->select("SELECT * FROM `field` WHERE `collectionId`=? AND `visibility`<=?
                                  ORDER BY `isCover` DESC,`isTitle` DESC,`isSubtitle` DESC,
                                  `preview` DESC,`order` DESC, `name`",
                                 [$id, $accessRights]);
        if (!$results) {
            $collection['fields'] = [];
            return $collection;
        }

        // get labels
        $fields = [];

        for ($i=0; $i < count($results); $i++) {
            $field = $results[$i];

            $field['label'] = [];
            $field['description'] = [];

            $labels = $this->select('SELECT `label`,`description`,`lang` FROM `fieldLabel`
                                     WHERE `fieldId`=?', [$field['id']]);
            if ($labels) {
                foreach ($labels as $label) {
                    $lang = $label['lang'];
                    $field['label'][$lang] = $label['label'];
                    $field['description'][$lang] = $label['description'];
                }
            }

            $name = $field['name'];

            // delete unecessary data
            unset($field['id']);
            unset($field['collectionId']);
            unset($field['name']);

            // map name as array key
            $fields[$name] = $field;
        }

        $collection['fields'] = $fields;
        return $collection;
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
     * Create collection
     * @param  array $data
     * @return int   Created collection ID
     */
    public function createCollection($data)
    {
        // filter data keys because labels are not in the same table
        $row = [];
        $fields = ['cover','owner','visibility'];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $row[$f] = $data[$f];
            }
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

        return true;
    }


    /**
     * Delete collection
     * @param  int  $id Collection ID
     * @param  bool $template Is template (optionnal)
     * @return bool Success
     */
    public function deleteCollection($id, bool $template=false)
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

        // get associated fields
        $fields = $this->selectColumn("SELECT `name` FROM `field` WHERE `collectionId`=?", [$id]);
        if ($fields === false) {
            return false;
        }

        // delete fields
        foreach ($fields as $field) {
            $this->deleteField($id, $field);
        }

        // delete collection labels
        $this->delete('collectionLabel', ['collectionId' => $id]);

        // delete collection
        return $this->delete('collection', ['template' => $template, 'id' => $id]);
    }


    /*
     * Collection templates
     */

    /**
     * Get collection template
     * @param  int    $id Collection template ID
     * @return array  Collection template data
     */
    public function getCollectionTemplate($id)
    {
        return $this->getCollection($id, true);
    }


    /**
     * Delete collection template
     * @param  int  $id Collection template ID
     * @return bool Success
     */
    public function deleteCollectionTemplate($id)
    {
        return $this->deleteCollection($id, true);
    }


    /*
     *  Items
     */

    /**
     * Get items of a collection
     * @param  int  $collectionId Collection ID
     * @return array|bool         Array of items IDs, FALSE if error
     */
    public function getItems($collectionId, $orderBy=null, $reverseOrder=false)
    {
        $query = "SELECT `id` FROM `item` WHERE `collectionId`=? AND `visibility`<=? ORDER BY `name`";
        $args = [$collectionId, $GLOBALS['accessRights']];

        if (!is_null($orderBy)) {
            $query = "SELECT i.id FROM `itemField` f JOIN `item` i ON f.itemId=i.id
                      WHERE f.collectionId=? AND i.visibility<=? AND f.name=? ORDER BY f.value";
            $args[] = $orderBy;
        }

        if ($reverseOrder) {
            $query .= ' DESC';
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

        $fields = $this->getItemFields($collectionId, $id);
        if (!$fields) {
            $item['fields'] = [];
        } else {
            $item['fields'] = $fields;
        }

        return $item;
    }


    /**
     * Get item fields
     * @param  int    $collectionId
     * @param  int    $itemId
     * @return array  SELECT results
     */
    public function getItemFields($collectionId, $itemId)
    {
        $result = $this->select('SELECT i.name AS `name`, i.value AS `value` FROM `itemField` i
                                 JOIN `field` f ON i.collectionId=f.collectionId AND i.name=f.name
                                 WHERE i.collectionId=? AND i.itemId=? AND f.visibility<=?
                                 ORDER BY `name`',
                                [$collectionId, $itemId, $GLOBALS['accessRights']]);
        if ($result === false) {
            return false;
        }

        // transform ['name' => 'fieldName', 'value' => Value] to 'fieldName' => 'Value'
        $fields = [];
        $name = '';
        foreach ($result as $field) {
            // same name than before: group field to array
            if ($field['name'] == $name) {
                if (is_array($fields[$name])) {
                    $fields[$name][] = $field['value'];
                } else {
                    $fields[$name] = [$fields[$name], $field['value']];
                }

                $name = $field['name'];
            } else {
                $name = $field['name'];
                $fields[$name] = $field['value'];
            }
        }

        return $fields;
    }


    /**
     * Get item field
     * @param  int    $collectionId
     * @param  int    $itemId
     * @param  string $name  Field name
     * @return array  SELECT results
     */
    public function getItemField(int $collectionId, int $itemId, string $name)
    {
        $fields = $this->selectColumn('SELECT i.value AS `value` FROM `itemField` i
                                       JOIN `field` f ON i.collectionId=f.collection AND i.name=f.name
                                       WHERE i.collectionId=? AND i.itemId=? AND f.name=? AND f.visibility<=?
                                       ORDER BY `name`',
                                      [$collectionId, $itemId, $name, $GLOBALS['accessRights']]);
        if (!$fields) {
            return false;
        }

        if (count($fields) == 0) {
            return null;
        } elseif (count($fields) == 1) {
            return $fields[0];
        }

        return $fields;
    }


    /**
     * Get item ID by property
     * @param  int    $collectionId
     * @param  string $name         Property name
     * @param  mixed  $value        Property value
     * @return array|bool Item data, FALSE if error
     */
    public function getItemByProperty(int $collectionId, string $name, $value)
    {
        $itemId = $this->selectOne("SELECT `itemId` FROM `itemField` WHERE `collectionId`=? AND `name`=? AND `value`=?",
                                   [$collectionId, $name, $value]);
        if (!$itemId) {
            return false;
        }

        return $this->getItem($collectionId, $itemId);
    }


    /**
     * Creates an item
     * @param  int   $collectionId
     * @param  array $data Item data (optionnal)
     * @return bool Success
     */
    public function createItem($collectionId, array $data=[])
    {
        $data['collectionId'] = $collectionId;
        return $this->insertOne('item', $data);
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
        if (isset($data['fields'])) {
            $fields = $data['fields'];

            foreach ($fields as $name => $field) {
                // update field
                if (!$this->setItemField($collectionId, $id, $name, $field)) {
                    Logger::warn('Failed to set item field '.$name.' for item '.$id.
                                 ' in collection '.$collectionId);
                }
            }

            unset($data['fields']);
        }

        if (count($data) == 0) {
            return true;
        }

        // update item
        return $this->update('item', $data, ['collectionId' => $collectionId, 'id' => $id]);
    }


    /**
     * Set Item name
     * @param  int    $id    Item ID
     * @param  string $name
     * @return bool   Success
     */
    public function setItemName(int $id, string $name)
    {
        return $this->update('item', ['name' => $name], ['id' => $id]);
    }


    /**
     * Delete item
     * @param  int $id Item ID
     * @return bool    Success
     */
    public function deleteItem(int $id)
    {
        return $this->delete('itemField', ['itemId' => $id]) && $this->delete('item', ['id' => $id]);
    }


    /**
     * Set item field
     * @param int $itemId  Item ID
     * @param string $name Field name
     * @param mixed $value Value
     * @return bool        Success
     */
    public function setItemField($collectionId, $itemId, $name, $value)
    {
        // delete old entries
        if (!$this->delete('itemField', ['collectionId' => $collectionId, 'itemId' => $itemId, 'name' => $name])) {
            return false;
        }

        // always convert value to array
        if (!is_array($value)) {
            $value = [$value];
        }

        $values = [];
        foreach ($value as $v) {
            // value null or empty: do nothing
            if (is_null($v) || $v == '') {
                continue;
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

        return ($this->insert('itemField', $data));
    }


    /*
     *  Fields
     */

    /**
     * Get collection field definition
     * @param  int    $collectionId Collection ID
     * @param  string $name         Field name
     * @return array                Array of data
     */
    public function getField($collectionId, $name)
    {
        // get fields
        $field = $this->selectFirst("SELECT * FROM `field` WHERE `collectionId`=? AND `name`=?",
                                    [$collectionId, $name]);
        if (!$field) {
            return false;
        }

        $field['label'] = [];
        $field['description'] = [];

        $labels = $this->select("SELECT `label`,`description`,`lang` FROM `fieldLabel`
                                 WHERE `fieldId`=?", [$field['id']]);
        if ($labels) {
            foreach ($labels as $label) {
                $lang = $label['lang'];
                $field['label'][$lang] = $label['label'];
                $field['description'][$lang] = $label['description'];
            }
        }

        return $field;
    }


    /**
     * Add field
     * @param  int    $collectionID
     * @param  string $name  Field name
     * @param  array  $data
     * @return bool   Inserted ID
     */
    public function createField($collectionID, $name, $data)
    {
        // filter data keys because labels are not in the same table
        $row = $data;
        $row['collectionId'] = $collectionID;
        $row['name'] = $name;
        unset($row['label']);
        unset($row['description']);

        // create field
        $fieldId = $this->insertOne('field', $row);
        if ($fieldId === false) {
            Logger::debug('Failed to insert new field: '.$name);
            return false;
        }

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

        foreach ($rows as &$row) {
            $row['field'] = $fieldId;
        }

        $orUpdate = [];
        if (count($data['label']) > 0) {
            $orUpdate[] = 'label';
        }
        if (count($data['description']) > 0) {
            $orUpdate[] = 'description';
        }

        // insert or update translations
        if (!$this->insert('fieldLabel', $rows, $orUpdate)) {
            Logger::var_dump($rows);
        }

        return true;
    }


    /**
     * Update field
     * @param  int    $collectionId
     * @param  string $name
     * @param  array  $data
     * @return bool   Success
     */
    public function updateField($collectionId, $name, $data)
    {
        // filter data keys because labels are not in the same table
        $row = $data;
        unset($row['label']);
        unset($row['description']);

        // update field table
        if (count($row) > 0) {
            if (!$this->update('field', $row, ['collectionId' => $collectionId, 'name' => $name])) {
                Logger::debug('Failed to update field');
                return false;
            }
        }

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

        // get field ID
        // update field table
        $fieldId = $this->selectOne("SELECT `id` FROM `field` WHERE `collectionId`=? AND `name`=?",
            [$collectionId, $name]
        );
        if (!$fieldId) {
            Logger::error("Failed to get field ID for collection ".$collectionId.", field: ".$name);
            return false;
        }

        foreach ($rows as &$row) {
            $row['fieldId'] = $fieldId;
        }

        $orUpdate = [];
        if (count($data['label']) > 0) {
            $orUpdate[] = 'label';
        }
        if (count($data['description']) > 0) {
            $orUpdate[] = 'description';
        }

        // insert or update translations
        if (!$this->insert('fieldLabel', $rows, $orUpdate)) {
            Logger::var_dump($rows);
        }

        return true;
    }


    /**
     * Delete field
     * @param  int $id Field ID
     * @return bool    Success
     */
    public function deleteField($collectionId, $name)
    {
        // delete item values of this field
        if (!$this->delete('itemField', ['collectionId' => $collectionId, 'name' => $name])) {
            return false;
        }

        // get field ID
        $id = $this->selectOne('SELECT `id` FROM `field` WHERE `collectionId`=? AND `name`=?',
                               [$collectionId, $name]);
        if (!$id) {
            return false;
        }

        // delete field labels
        if (!$this->delete('fieldLabel', ['fieldId' => $id])) {
            return false;
        }

        // delete field
        return $this->delete('field', ['id' => $id]);
    }


    /*
     *  Notifications
     */

    /**
     * Insert notification
     * @param string $type
     * @param string $text
     * @return bool  Success
     */
    public function addNotification($type, $text)
    {
        return $this->insertOne('notification', ['type' => $type, 'text' => $text]);
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
        if ($this->count('collection', ['cover' => $path]) > 0) {
            return true;
        }

        // search in items
        if ($this->count('itemField', ['value' => $path]) > 0) {
            return true;
        }

        // search in user avatars
        if ($this->count('user', ['avatar' => $path]) > 0) {
            return true;
        }

        return false;
    }


    /*
     *  Users
     */

    /**
     * Get user by username
     * @param  string $username
     * @return array  Result
     */
    public function getUserByName($username)
    {
        return $this->selectFirst("SELECT * FROM `user` WHERE `username`=?", [$username]);
    }


    /**
     * Get user by login
     * @param  string $username
     * @param  string $password
     * @return array Result
     */
    public function getUserByLogin($username, $password)
    {
        // TODO: change crypt method; checking only the 8 first characters
        return $this->selectFirst(
            "SELECT * FROM `user` WHERE `username`=? AND `password`=?",
            [$username, crypt($password, DB_SALT)]
        );
    }


    /**
     * Get user by password reset token
     * @param  string $token
     * @return array  Result
     */
    public function getUserByResetToken($token)
    {
        return $this->selectFirst("SELECT * FROM `user` WHERE `resetToken`=?", [$token]);
    }


    /**
     * Create user
     * @param  string $username
     * @param  string $password
     * @return bool   Success
     */
    public function createUser($username, $password)
    {
        return $this->insertOne('user',
            ['username' => $username, 'password' => crypt($password, DB_SALT)]
        );
    }


    /**
     * Set user password
     * @param  int    $userID
     * @param  string $password
     * @return bool   Success
     */
    public function setUserPassword($userID, $password)
    {
        return $this->update('user',
            ['password' => crypt($password, DB_SALT)], ['id' => $userID]
        );
    }


    /**
     * Set user password reset token
     * @param  int    $userID
     * @param  string $token
     * @return bool   Success
     */
    public function setUserResetToken($userID, $token)
    {
        return $this->update('user',
            ['resetToken' => $token], ['id' => $userID]
        );
    }


    /**
     * Count number of users
     * @return int
     */
    public function countUsers()
    {
        return $this->count('user');
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

        /*// Example:
        if (Config::compareVersions('1.0.1', $currentVersion)) {
            echo "Migrate database from '$currentVersion' to '1.0.1'...";
            // do changes in database...
        }*/

        return $this->setConfig('version', $newVersion);
    }


    /************************
     *  DATABASE SQL UTILS  *
     ************************/

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
        $query = 'SELECT COUNT(*) FROM `'.$table.'`';

        if (count($filters) > 0) {
            $query .= ' WHERE ';

            foreach ($filters as $key => $value) {
                $query .= $key.'=? AND ';
            }

            // delete last " AND "
            $query = substr($query, 0, -5);
        }

        // runs query
        return $this->selectOne($query, $filters);
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
            $query .= '`'.$key.'`=?,';
        }
        // delete last ","
        $query = substr($query, 0, -1);

        if (count($filters) > 0) {
            $query .= ' WHERE ';

            foreach ($filters as $key => $v) {
                $query .= '`'.$key.'`=? AND ';
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
                $query .= '`'.$key.'`=? AND ';
            }

            // delete last " AND "
            $query = substr($query, 0, -5);
        }

        return $this->write($query, $filters);
    }
}
