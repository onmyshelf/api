<?php

class GlobalDatabase
{
    /**
     * Install database
     * @return bool Success
     */
    public function install()
    {
        return $this->setConfig('version', VERSION);
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
