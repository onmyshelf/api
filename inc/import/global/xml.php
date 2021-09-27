<?php
/*
 * XML file import
 */

class XmlImport extends GlobalImport
{
    protected $xml;

    /**
     * Class constructor
     * @param string $file    The path to the XML file to import
     * @param array  $options Import options
     */
    public function __construct($file, $options=[])
    {
        $file = Storage::path($file);

        if (!file_exists($file)) {
            Logger::error("Import XML: file ".$file." does not exists!");
            throw new Exception();
            return;
        }

        libxml_use_internal_errors(true);

        // read and load XML
        if (($this->xml = simplexml_load_file($file)) === false) {
            Logger::fatal("Error while loading XML file ".$file);
            throw new Exception();
            return;
        }

        parent::__construct($file, $options);
    }
}
