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
  exit();
}

// run install
$db->install();

// create default user
$db->createUser('onmyshelf', 'onmyshelf');
