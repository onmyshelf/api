<?php
/*
 * JSON file import
 */

abstract class JsonImport extends GlobalImport
{
    protected $json;

    /**
     * Class constructor
     * @param string $file    The path (or URL) to the JSON file to import
     * @param array  $options Import options
     */
    public function __construct($file, $options=[])
    {
        $file = Storage::path($file);

        if (!preg_match('/^https*:\/\//', $file) && !file_exists($file)) {
            Logger::error("Import JSON: file ".$file." does not exists!");
            throw new Exception();
            return;
        }

        $content = file_get_contents($file);
        $this->json = json_decode($content);

        parent::__construct($file, $options);
    }
}
