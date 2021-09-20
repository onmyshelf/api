<?php

class GlobalDatabase
{
    /**
     * Install database
     * @return bool Install success
     */
    public function install()
    {
        return true;
    }


    /**
     * Upgrade database
     * @param string $newVersion
     * @return bool  Upgrade success
     */
    public function upgrade($newVersion)
    {
        return true;
    }
}
