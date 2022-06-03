<?php

class Module
{
    /**
     * Load a module
     * @param  string $type
     * @param  string $name
     * @return bool         Success
     */
    public static function load(string $type, string $name)
    {
        // check type (security)
        switch ($type) {
          case 'import':
            // ok
            break;

          default:
            Logger::error("Bad module type: $type");
            return false;
            break;
        }

        // check name (security)
        if (!preg_match('/^[a-z-]+$/', $name)) {
            Logger::error("Bad $type module name: $name");
            return false;
        }

        // load main class
        require_once("inc/$type/global/$type.php");

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

        // load internal class if exists
        if (file_exists("inc/$type/$name.php")) {
            require_once("inc/$type/$name.php");
            return true;
        }

        Logger::error("Unknown $type type: $name");
        return false;
    }
}
