<?php
/*
*  MySQL/MariaDB database support
*/

require_once('sqlDatabase.php');

class Database extends SqlDatabase
{
    /**
     * Upgrade procedure for v1.1.0
     * @return bool Success
     */
    protected function upgrade_v110() {
        // add created/updated columns
        foreach (['collection', 'item'] as $table) {
            $sql = "ALTER TABLE `$table`
                    ADD COLUMN IF NOT EXISTS `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ADD COLUMN IF NOT EXISTS `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

            if (!$this->execute($sql)) {
                Logger::fatal("Upgrade v1.1.0: Failed to add created/updated columns to $table table");
                return false;
            }
        }

        // add item.name column
        $sql = "ALTER TABLE `item`
                ADD COLUMN IF NOT EXISTS `name` varchar(255) DEFAULT NULL AFTER `collectionId`";

        if (!$this->execute($sql)) {
            Logger::fatal("Upgrade v1.1.0: Failed to add item name column");
            return false;
        }

        // get title properties of each collection
        $titleProperties = $this->select("SELECT `collectionId`, `name` AS `titleProperty` FROM `property` WHERE `isTitle`=1");
        
        // for each collection where title property is defined,
        foreach ($titleProperties as $collection) {
            // get all items of this collection
            $items = $this->selectColumn("SELECT `id` FROM `item` WHERE `collectionId`=?", [$collection["collectionId"]]);
            foreach ($items as $itemId) {
                // set item name from the title property
                if (!$this->write("UPDATE `item`
                                SET `name`=(SELECT `value` FROM `itemProperty` WHERE `collectionId`=? AND `itemId`=? AND `name`=?)
                                WHERE `id`=?", [$collection["collectionId"], $itemId, $collection["titleProperty"], $itemId])) {
                    Logger::error("Upgrade v1.1.0: Failed to set item name for item $itemId");
                }
            }
        }

        return true;
    }


    /**
     * Creates a prepared query, binds the given parameters and returns the result of the executed
     * @param  string $table    Table name
     * @param  array  $values   Array or arrays key => value
     * @param  array  $orUpdate Array of fields to update on duplicate key
     * @return bool
     */
    protected function insert(string $table, array $values, array $orUpdate=[])
    {
        if (count($values) == 0) {
            return false;
        }

        $query = 'INSERT INTO `'.$table.'` (';

        foreach ($values[0] as $key => $v) {
            $query .= '`'.$key.'`,';
        }
        // delete last comma
        $query = substr($query, 0, -1).') VALUES ';

        // avoid errors if data not correct (missing keys)
        try {
            $queryValues = '('.implode(',', array_values(array_fill(0,count($values[0]),'?'))).'),';
        } catch (Throwable $t) {
            Logger::fatal('Bad array in insert values');
            return false;
        }

        $query .= str_repeat($queryValues, count($values));
        // delete last comma
        $query = substr($query, 0, -1);

        // or update
        if (count($orUpdate) > 0) {
            $query .= ' ON DUPLICATE KEY UPDATE ';
            foreach ($orUpdate as $key) {
                // WARNING: this is working only from MariaDB 10.3.3!
                $query .= '`'.$key.'`=VALUE(`'.$key.'`),';
            }
            // delete last comma
            $query = substr($query, 0, -1);
        }

        $params = [];
        foreach ($values as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }

        if (!$this->write($query, $params)) {
            return false;
        }

        // returns last inserted ID if any
        if ($this->connection->lastInsertId()) {
            return $this->connection->lastInsertId();
        }

        return true;
    }
}
