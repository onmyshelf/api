<?php

/**
 * Test import module
 *
 * @param string $module Name of the module
 * @param string $search Text to search
 * @param string $match  String to match in first result
 * @param array $options Options (tags, language, ...)
 * @param string $label  Label for the test
 * @return void
 */
function testModuleImportHtmlSearch($module, $search, $match=null, $options=[], $label='') {
    test("Import module $module: search '$search'".($label ? " ($label)" : ""), function ($module, $search, $match, $options, $label) {

        $import = new Import($module);
        $import->load();
        $results = $import->search($search, $options);

        expect($results)
            ->toBeArray();

        expect(count($results))
            ->toBeGreaterThan(0);

        expect($results[0])
            ->toBeArray()
            ->toHaveKeys(['source', 'name', 'image', 'description']);

        expect($results[0]['source'])
            ->toMatch('/^https:\/\//');

        if (!$match)
            $match = "/$search/i";

        expect($results[0]['name'])
            ->toMatch($match);

        expect($results[0]['image'])
            ->toMatch('/\/\//');

        // get data from the first result
        $item = $import->getData($results[0]['source']);

        expect($item)
            ->toBeArray()
            ->toHaveKey('source');

        expect($item['source'])
            ->toMatch('/^http/');
    })->with([[$module, $search, $match, $options, $label]]);
}


/**
 * Test import module
 *
 * @param string $module Name of the module
 * @param string $search Text to search
 * @param string $match  String to match in first result
 * @param array $options Options (tags, language, ...)
 * @param string $label  Label for the test
 * @return void
 */
function testModuleImportHtmlData($module, $source, $match, $options=[], $label='') {
    test("Import module $module: get data from '$source'".($label ? " ($label)" : ""), function ($module, $source, $match, $options, $label) {

        $import = new Import($source, $options);
        $import->load();
        $data = $import->getData();

        expect($data)
            ->toBeArray()
            ->toHaveKeys(array_keys($match));

        foreach ($match as $key => $value) {
            expect($data[$key])
                ->toMatch($value);
        }
    })->with([[$module, $source, $match, $options, $label]]);
}

// search & run tests for external modules
$modules = glob("./inc/modules/import/test/tests/test.php");
foreach ($modules as $module) {
    include dirname($module)."/../import.php";
    require_once $module;
}
