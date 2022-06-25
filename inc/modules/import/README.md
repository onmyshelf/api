# Import modules

You can add here a custom import module.

# Structure
Creates a folder here with a file named `import.php`.

You must create your file with at least:
- `scanFields()`: method that detects fields
- `import()`: method that does the import

e.g. of file:
```php
<?php
class Import extends ImportGlobal {
    /**
     * Print information about the module
     * @return array
     */
    public function info()
    {
        return [
            "type" => "movies",
            "label" => "IMDB",
            "description" => "The Internet Movie DataBase"
        ];
    }

    /**
     * Return item data
     * @return array
     */
    public function getData()
    {
        ...
        return [...];
    }

    /**
     * Import
     * @return int|bool  Number of items imported, FALSE if error
     */
    public function import($idCollection, $mapping=[])
    {
        ...
    }
}
```
