<?php

/*
 *  Database
 */

// Database type
// Native support: mysql
define('DATABASE', 'mysql');

// Database credentials
define('DB_HOST', 'db');
define('DB_NAME', 'onmyshelf');
define('DB_USER', 'onmyshelf');
define('DB_PASSWORD', 'onmyshelf');


/*
 *  Localization
 */

// default language
define('DEFAULT_LANG', 'en_US');


/*
 *  API access
 */

// API URL
// You can put relative paths (e.g. /api) or complete URL (e.g. https://myapi.com/api)
// DO NOT PUT A "/" AT THE END OF THE PATH!
// Leave empty to put the API at your domain root.
define('API_URL', '/api/v1');


/*
 *  Media library
 */

// Storage type
define('STORAGE', 'local');

// Directoy path of the media library
// Default: ../../media (starts from /api/v1)
define('MEDIA_DIR', '../../media');

// Public URL of the media library
// You can put relative paths (e.g. /media) or complete URL (e.g. https://myapi.com/media)
// DO NOT PUT A "/" AT THE END OF THE PATH!
define('MEDIA_URL', '/media');


/*
 *  Advanced configuration
 */

// Token retention time (in minutes)
define('TOKEN_LIFETIME', 600);

// Path of the log file
define('LOGFILE', 'onmyshelf.log');

// Set log level
define('LOGGER_LEVEL', 'INFO');

// Read only mode; authentication disabled
// Useful for demo websites
define('READ_ONLY', false);
