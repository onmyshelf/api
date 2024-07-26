<?php

abstract class GlobalDatabase
{
    /*
     * METHODS
     */

    // authentication
    abstract public function createToken($token, $userId, $expiration, $ipOrigin, $type);
    abstract public function deleteToken($token);
    abstract public function deleteUserTokens($userId, $type);
    abstract public function cleanupTokens();

    // config
    abstract public function dumpConfig();
    abstract public function getConfig($param);
    abstract public function setConfig($param, $value, $locked);

    // collections
    abstract public function getCollections($owner, $isTemplate);
    abstract public function setCollectionUpdated($collectionId);
    abstract public function getCollection($id, $isTemplate);
    abstract public function getCollectionTags($id);
    abstract public function setCollectionTags($id, $tags);
    abstract public function createCollection($data);
    abstract public function updateCollection($id, $data);
    abstract public function deleteCollection($id);

    /*
     * Collection templates
     */

    /**
     * Get collection templates
     * @param  int   $owner  Show only owner (optional)
     * @return array         Collection templates
     */
    public function getCollectionTemplates($owner=null)
    {
        return $this->getCollections($owner, true);
    }


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
     * Create collection template
     *
     * @param array $data
     * @return bool Success
     */
    public function createCollectionTemplate($data)
    {
        $data['template'] = true;
        return $this->createCollection($data);
    }


    /**
     * Update collection template
     *
     * @param int   $id
     * @param array $data
     * @return bool Success
     */
    public function updateCollectionTemplate($id, $data)
    {
        $data['template'] = true;
        return $this->updateCollection($id, $data);
    }


    /**
     * Delete collection template
     * @param  int  $id Collection template ID
     * @return bool Success
     */
    public function deleteCollectionTemplate($id)
    {
        return $this->deleteCollection($id);
    }

    // items
    abstract public function getItems($collectionId, $sortBy);
    abstract public function getItem($collectionId, $id);
    abstract public function getItemProperties($collectionId, $itemId);
    abstract public function getItemProperty($collectionId, $itemId, $name);
    abstract public function getItemByProperty($collectionId, $name, $value);
    abstract public function setItemUpdated($collectionId, $itemId);
    abstract public function createItem($collectionId, $data);
    abstract public function updateItem($collectionId, $id, $data);
    abstract public function deleteItem($id);
    abstract public function setItemProperty($collectionId, $itemId, $name, $value);

    // properties
    abstract public function getProperty($collectionId, $name);
    abstract public function setProperty($collectionId, $name, $data);
    abstract public function deleteProperty($collectionId, $name);

    // loans
    abstract public function getItemLoans($itemId);
    abstract public function getLoan($id);
    abstract public function isItemLent($itemId);
    abstract public function createLoan($data);
    abstract public function updateLoan($id, $data);
    abstract public function deleteLoan($id);

    // notifications
    abstract public function addNotification($userId, $type, $text);

    // storage
    abstract public function mediaExists($path);

    // users
    abstract public function getUsers();
    abstract public function getUserById($id);
    abstract public function getUserByLogin($login);
    abstract public function getUserByToken($token, $type);
    abstract public function createUser($data);
    abstract public function updateUser($id, $data);
    abstract public function deleteUser($id);


    /**
     * Install database
     * @return bool Success
     */
    public function install()
    {
        // check if database version is set
        // Note: (new Database) is because we need to reset database connection after install
        if ((new Database)->getConfig('version') !== false) {
            return true;
        }
        
        // first installation: set version
        return (new Database)->setConfig('version', VERSION, true);
    }


    /**
     * Upgrade database
     * @param  string Version to upgrade to
     * @return bool   Success
     */
    public function upgrade($newVersion)
    {
        return $this->setConfig('version', $newVersion, true);
    }


    abstract public function init();
}
