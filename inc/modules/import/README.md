# Import modules

You can add here a custom import module.

You must create your file with at least:
- `scanFields()`: method that detects fields
- `import()`: method that does the import

e.g. of file:
```php
<?php
class Import extends ImportGlobal {

    /**
     * Analyse fields available
     * @return void
     */
    public function scanFields()
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
