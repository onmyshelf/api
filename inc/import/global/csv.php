<?php

abstract class CsvImport extends GlobalImport
{
    protected $separator;
    protected $handle;


    /**
     * Load the CSV file
     *
     * @return boolean Success
     */
    public function load()
    {
        $file = Storage::urlToPath($this->source);

        if (!file_exists($file)) {
            Logger::error('CSV file does not exists!');
            return false;
        }

        // default separator
        $this->separator = ',';

        // if separator defined in options, use it
        if (isset($options['separator'])) {
            $this->separator = (string) $options['separator'];
        }

        return true;
    }


    /**
     * Scan properties of the CSV
     * @return array|bool  Array of properties, FALSE if error
     */
    public function getProperties()
    {
        // if already defined
        if (count($this->properties) > 0) {
            return $this->properties;
        }

        // open CSV file
        if (($csv = $this->openCSV()) === false) {
            return false;
        }

        // read first row to get properties
        if (($data = fgetcsv($csv, 0, $this->separator)) !== false) {
            foreach ($data as $key) {
                $this->properties[] = $key;
            }
        }

        fclose($csv);

        return $this->properties;
    }


    /**
     * Import data into collection
     * @return bool Import success
     */
    public function import($collection)
    {
        // open CSV file
        if (($csv = $this->openCSV()) === false) {
            return false;
        }

        // read CSV line by line
        $row = 0;
        while (($data = fgetcsv($csv, 0, $this->separator)) !== false) {
            $row++;

            // first line:
            if ($row == 1) {
                // reset properties
                $this->properties = [];
                // get them
                foreach ($data as $key) {
                    $this->properties[] = $key;
                }
                // jump to 2nd line
                continue;
            }

            // import properties
            $properties = [];
            for ($i=0; $i < count($data); $i++) {
                $key = $this->properties[$i];
                $properties[$key] = $data[$i];
            }

            // import item
            $this->importItem($collection, $properties);
        }

        fclose($csv);

        return true;
    }


    protected function openCSV()
    {
        // open CSV file
        try {
            if (($handle = fopen(Storage::urlToPath($this->source), 'r')) === false) {
                Logger::error("failed to load CSV: $this->source");
                return false;
            }
        } catch (Throwable $t) {
            Logger::error("failed to load CSV: $this->source");
            return false;
        }

        return $handle;
    }
}
