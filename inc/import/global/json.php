<?php
/*
 * JSON file import
 */

abstract class JsonImport extends GlobalImport
{
    protected $json;


    /**
     * Load the JSON file
     *
     * @return boolean Success
     */
    public function load()
    {
        $file = Storage::path($this->source);

        if (!preg_match('/^https*:\/\//', $file) && !file_exists($file)) {
            Logger::error("Import JSON: file ".$file." does not exists!");
            throw new Exception();
            return;
        }

        $content = file_get_contents($file);
        $this->json = json_decode($content);

        return true;
    }
}
