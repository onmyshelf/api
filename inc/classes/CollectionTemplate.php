<?php

class CollectionTemplate extends Collection
{
    // disable parent method
    public function addItem($data=null)
    {
        return false;
    }

    // disable parent method
    public function setItems(array $items)
    {
        return false;
    }

    // disable parent method
    public function import($type, $source, $options=[])
    {
        return false;
    }


    /********************
    *  STATIC METHODS  *
    ********************/

    /**
     * Dump all templates
     * @param  int   $userId (optional)
     * @return array
     */
    public static function dumpAll($userId=null)
    {
        return (new Database)->getCollectionTemplates($userId);
    }
}
