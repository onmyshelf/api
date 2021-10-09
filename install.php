<?php

// load dependencies
require_once('inc/init.php');

// test database connection
try {
  $db = new Database();
} catch (Throwable $t) {
  echo "ERROR: cannot connect to database!";
  Logger::fatal("error while initializing database connection: ".$t);
  exit(1);
}

// get database version
if ($db->getConfig('version')) {
    echo "Already installed";
    unlink(__FILE__);
    exit();
}

echo "Initialize database...";
if (!$db->install()) {
    echo " FAILED!"
    exit();
}

// create default user
if ($db->countUsers() == 0) {
    echo "\nCreate default user..."
    if (!$db->createUser('onmyshelf', 'onmyshelf')) {
        echo " FAILED!"
        exit();
    }
}

echo "\nInstall finished.";

// delete itself
unlink(__FILE__);
