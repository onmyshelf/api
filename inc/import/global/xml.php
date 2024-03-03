<?php
/*
 * XML file import
 */

abstract class XmlImport extends GlobalImport
{
    protected $xml;


    /**
     * Load the XML file
     *
     * @return boolean Success
     */
    public function load()
    {
        $file = Storage::urlToPath($this->source);

        if (!file_exists($file)) {
            Logger::error("Import XML: file ".$file." does not exists!");
            return false;
        }

        libxml_use_internal_errors(true);

        // read and load XML
        if (($this->xml = simplexml_load_file($file)) === false) {
            Logger::error("Error while loading XML file ".$file);
            return false;
        }

        return true;
    }
}
