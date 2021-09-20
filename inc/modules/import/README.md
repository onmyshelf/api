# Import modules

You can add here a custom import module.

You must create your file with at least:
- `scanFields()`: method that fills `$this->fields` variable
- `import()`: method that does the import

e.g. of file:
```php
<?php
class Import extends ImportGlobal {

  /**
   * Analyse fields of the collection
   * Fills $this->fields array
   * @return void
   */
  public function scanFields()
  {
    ...
    $this->fields = [...];
  }

  /**
   * Analyse fields of the collection
   * Fills $this->fields array
   * @return int|bool  Number of items imported, FALSE if error
   */
  public function import($idCollection, $mapping=[])
  {
    ...
  }
}
```
