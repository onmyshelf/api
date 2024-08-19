<?php
define('VERSION', '1.4.0-beta.1+2024081901');

// load config file
if (!file_exists("config.php")) {
    Logger::fatal("Config file not found! Please create it");
    exit(1);
}
require_once('config.php');

// load utils
require_once('logger.php');
require_once('emails/mailer.php');
require_once('notification.php');

// check database type (security)
if (!preg_match('/^\w+$/', DATABASE)) {
    Logger::fatal("bad database type: ".DATABASE);
    exit(1);
}

// load main database class
require_once('inc/database/global/database.php');

switch (DATABASE) {
    case 'mysql':
        // native database modules
        require_once("inc/database/".DATABASE.".php");
        break;

    default:
        if (file_exists("inc/modules/database/".DATABASE."/database.php")) {
            try {
                require_once("inc/modules/database/".DATABASE."/database.php");
            } catch (Throwable $t) {
                Logger::fatal("error while loading database module: ".DATABASE);
                exit(1);
            }
        }
        break;
}

// load main storage class
require_once('inc/storage/global/storage.php');

switch (STORAGE) {
    case 'local':
        // native storage modules
        require_once("inc/storage/".STORAGE.".php");
        break;

    default:
        if (file_exists("inc/modules/storage/".STORAGE."/storage.php")) {
            try {
                require_once("inc/modules/storage/".STORAGE."/storage.php");
            } catch (Throwable $t) {
                Logger::fatal("error while loading storage module: ".STORAGE);
                exit(1);
            }
        }
        break;
}

// load classes
require_once('classes/Borrower.php');
require_once('classes/Config.php');
require_once('classes/Collection.php');
require_once('classes/CollectionTemplate.php');
require_once('classes/Item.php');
require_once('classes/Loan.php');
require_once('classes/Module.php');
require_once('classes/Property.php');
require_once('classes/User.php');
require_once('classes/Visibility.php');
