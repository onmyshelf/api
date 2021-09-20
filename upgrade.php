<?php

// load dependencies
require_once('inc/init.php');

// get current version
$currentVersion = Config::get('version');

if (!$currentVersion) {
    echo "ERROR: cannot get current version!";
    exit(1);
}

// run upgrade
if (Config::compareVersions(VERSION, $currentVersion)) {
    echo "Upgrading to ".VERSION."...";

    // upgrade version (without build number)
    (new Database())->upgrade(preg_replace('/\+.*/', '', VERSION));
} else {
    echo "Already up-to-date";
}
