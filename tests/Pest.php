<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

define('MEDIA_DIR', '/tmp/pest');

require_once __DIR__ . "/../inc/logger.php";
require_once __DIR__ . "/../inc/storage/global/storage.php";
require_once __DIR__ . "/../inc/storage/local.php";
require_once __DIR__ . "/../inc/import/global/import.php";

// search & run tests for external modules
//$modules = glob(__DIR__ . "/../inc/import/*/import.php");
/*foreach ($modules as $module) {
    // import
    //if (!str_contains('/global/import.php', $module)) {
    //    error_log("import: $module");
        require_once $module;
    //}
}

/*
// load module if exists
if (file_exists("inc/modules/$type/$name/import.php")) {
    try {
        require_once("inc/modules/$type/$name/import.php");
        return true;
    } catch (Throwable $t) {
        Logger::fatal("Error while loading $type module: $name");
        return false;
    }
}
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
