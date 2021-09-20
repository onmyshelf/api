<?php

// load dependencies
require_once('inc/init.php');
require_once('inc/api.php');

// load API
(new Api())->route();
