<?php

class Module
{
    /**
     * Returns available modules
     * @param string $type  Type of module
     * @return void
     */
    public static function list(string $type)
    {
        // check type (security)
        switch ($type) {
            case 'import':
                break;

            default:
                Logger::error("Bad module type: $type");
                return [];
        }

        $modules = [];

        // list all modules
        $moduleFiles = array_merge(
            glob("inc/$type/*/$type.php"),
            glob("inc/modules/$type/*/$type.php")
        );

        foreach ($moduleFiles as $path) {
            $module = basename(dirname($path));

            // ignore global
            if ($module == 'global') {
                continue;
            }

            // default name
            $info = new stdClass();
            $info->name = ucfirst($module);
            
            // get module information from info.json
            $infofile = dirname($path)."/info.json";
            if (file_exists($infofile)) {
                $file = file_get_contents($infofile);
                if ($file) {
                    $info = json_decode($file);
                }
            }

            // get if external project or not
            if (basename(dirname($path, 3)) == 'modules') {
                $info->external = true;
            }

            $modules[$module] = $info;
        }

        // sort modules by name
        ksort($modules);

        return $modules;
    }


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
                break;

            default:
                Logger::error("Bad module type: $type");
                return false;
                break;
        }

        // check name (security)
        if (!preg_match('/^[a-zA-Z-]+$/', $name)) {
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
        if (file_exists("inc/$type/$name/import.php")) {
            require_once("inc/$type/$name/import.php");
            return true;
        }

        Logger::error("Unknown $type type: $name");
        return false;
    }
}
