# Import modules

You can add here a custom import module.

# Creating a module
First you have to create a directory with a unique name which will be your module name.
Do not use special characters.

Then you have to create 2 files: `info.json` and `import.php`.

## The info.json file
This file contains informations about your module.

e.g. for `info.json` file:
```json
{
  "name": "My import source",
  "description": "This is my module to import from...",
  "version": "1.0.0",
  "search": true,
  "tags": [
    "books",
    "comics"
  ]
}
```

The `search` field is needed when you want your module to be used to search in a catalog.
The `tags` field contains a list of types of collections that are compatible with.

You can see the default [collection templates definition here](../../database/init/collectionTemplates.json).

## The import.php file
This file is a class definition where you can implement your import features.

Your class must be named as `Import` and must extends a global import class (see [available global classes here](../../import/global/)).

e.g. of `import.php` file for a HTML parser:
```php
<?php
require_once('inc/import/global/html.php');

class Import extends HtmlImport
{
    public function load()
    {
        // properties this module can return
        $this->properties = [
            'source',
            'title',
            ...
        ];

        return true;
    }

    /**
     * Search items
     * @param  string $text
     * @return array  Array of results
     */
    public function search($text)
    {
        // Note: you must follow this structure
        return [
            [
                'source' => "...",
                'name' => "...",
                'image' => "...",
                'description' => "...",
            ],
            ...
        ];
    }

    /**
     * Get item data from $this->source
     * @return array Item properties values
     */
    public function getData()
    {
        return [
            'source' => "...",
            'title' => "...",
            ...
        ];
    }
}
```

# Explore modules
Some modules are available here: https://gitlab.com/onmyshelf/modules
