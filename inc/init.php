<?php

define('VERSION', '1.0.0-beta.1');

// load config file
if (!file_exists("config.php")) {
    Logger::fatal("Config file not found! Please create it");
    exit(1);
}
require_once('config.php');

// load utils
require_once('logger.php');
require_once('notification.php');

// check database type (security)
if (!preg_match('/^\w+$/', DATABASE)) {
    Logger::fatal("bad database type: ".DATABASE);
    exit(1);
}

// load main database class
require_once('inc/database/global/database.php');

// load internal database class if exists
if (file_exists("inc/database/".DATABASE.".php")) {
    require_once("inc/database/".DATABASE.".php");
} else {
    // load module if exists
    if (file_exists("inc/modules/database/".DATABASE.".php")) {
        try {
            Logger::debug("Use database module: ".DATABASE);
            require_once("inc/modules/database/".DATABASE.".php");
        } catch (Throwable $t) {
            Logger::fatal("error while loading database module: ".DATABASE);
            exit(1);
        }
    } else {
        Logger::fatal("unknown database type: ".DATABASE);
        exit(1);
    }
}

// load main storage class
require_once('inc/storage/storage.php');

// load classes
require_once('classes/Config.php');
require_once('classes/Collection.php');
require_once('classes/CollectionTemplate.php');
require_once('classes/Field.php');
require_once('classes/Item.php');
require_once('classes/User.php');
