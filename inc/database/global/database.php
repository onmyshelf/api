<?php

abstract class GlobalDatabase
{
    /*
     * METHODS
     */

    // authentication
    abstract public function createToken($token, $userId, $expiration, $ipOrigin, $type);
    abstract public function deleteToken($token);
    abstract public function cleanupTokens();

    // config
    abstract public function dumpConfig();
    abstract public function getConfig($param);
    abstract public function setConfig($param, $value);

    // collections
    abstract public function getCollections($owner, $template);
    abstract public function getCollection($id, $template);
    abstract public function createCollection($data);
    abstract public function updateCollection($id, $data);
    abstract public function deleteCollection($id, $template);

    // collection templates
    abstract public function getCollectionTemplate($id);
    abstract public function deleteCollectionTemplate($id);

    // items
    abstract public function getItems($collectionId, $sortBy);
    abstract public function getItem($collectionId, $id);
    abstract public function getItemProperties($collectionId, $itemId);
    abstract public function getItemProperty($collectionId, $itemId, $name);
    abstract public function getItemByProperty($collectionId, $name, $value);
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
    abstract public function addNotification($type, $text);

    // storage
    abstract public function mediaExists($path);

    // users
    abstract public function getUserByName($username);
    abstract public function getUserByLogin($username, $password);
    abstract public function getUserByToken($token, $type);
    abstract public function createUser($username, $password);
    abstract public function setUserPassword($userId, $password);
    abstract public function countUsers();


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
        return (new Database)->setConfig('version', VERSION);
    }


    /**
     * Upgrade database
     * @param  string Version to upgrade to
     * @return bool   Success
     */
    public function upgrade($newVersion)
    {
        return $this->setConfig('version', $newVersion);
    }
}
