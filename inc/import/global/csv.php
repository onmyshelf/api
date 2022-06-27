<?php
class CsvImport extends GlobalImport
{
    private $separator;

    /**
     * Class constructor
     * @param string $file    The path to the CSV file
     * @param array  $options Import options
     */
    public function __construct($file, $options=[])
    {
        $file = Storage::path($file);

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

        // call parent constructor
        parent::__construct($file, $options);
    }


    /**
     * Scan fields of the CSV
     * @return array|bool  Array of fields, FALSE if error
     */
    public function scanFields()
    {
        $fields = [];

        // open CSV file
        try {
            if (($handle = fopen($this->source, 'r')) === false) {
                Logger::error("failed to load CSV: $this->source");
                return false;
            }
        } catch (Throwable $t) {
            Logger::error("failed to load CSV: $this->source");
            return false;
        }

        // read first row to get fields
        if (($data = fgetcsv($handle, 1000, $this->separator)) !== false) {
            foreach ($data as $key) {
                $fields[] = $key;
            }
        }

        // close file
        fclose($handle);

        return $fields;
    }


    /**
     * Import data into collection
     * @return bool Import success
     */
    public function import()
    {
        // open CSV file
        try {
            if (($handle = fopen($this->source, 'r')) === false) {
                Logger::error("failed to load CSV: $this->source");
                return false;
            }
        } catch (Throwable $t) {
            Logger::error("failed to load CSV: $this->source");
            return false;
        }

        // read CSV line by line
        $row = 0;
        while (($data = fgetcsv($handle, 0, $this->separator)) !== false) {
            $row++;

            // ignore first line (headers)
            if ($row == 1) {
                continue;
            }

            // import fields
            $fields = [];
            for ($i=0; $i < count($data); $i++) {
                $key = $this->fields[$i];
                $fields[$key] = $data[$i];
            }

            // import item
            $this->importItem($fields);
        }

        // close file
        fclose($handle);

        return true;
    }
}
