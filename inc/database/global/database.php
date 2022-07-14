<?php

abstract class GlobalDatabase
{
    /**
     * Install database
     * @return bool Success
     */
    public function install()
    {
        // we need to reset database connection after install
        return (new Database())->setConfig('version', VERSION);
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
