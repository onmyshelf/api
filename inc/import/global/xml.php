<?php
/*
 * XML file import
 */

abstract class XmlImport extends GlobalImport
{
    protected $xml;

    /**
     * Class constructor
     * @param string $file    The path to the XML file to import
     * @param array  $options Import options
     */
    public function load()
    {
        $file = Storage::path($this->source);

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
