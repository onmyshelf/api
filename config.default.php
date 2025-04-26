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
 *  AI
 */

// AI engine
define('AI', 'openai');


/*
 *  Email configuration (optional)
 */

// Email address from
define('EMAIL_FROM', '');

// SMTP server
define('SMTP_SERVER', '');

// SMTP port
define('SMTP_PORT', '465');

// SMTP user
define('SMTP_USER', '');

// SMTP password
define('SMTP_PASSWORD', '');


/*
 *  Advanced configuration
 */

// OnMyShelf public URL (optionnal)
define('OMS_URL', '');

// Token retention time (in minutes)
define('TOKEN_LIFETIME', 43200);

// Path of the log file
define('LOGFILE', 'onmyshelf.log');

// Set log level
define('LOGGER_LEVEL', 'INFO');

// Dev mode: enable cross-origin requests
define('DEV_MODE', false);

// Read only mode: disable every API write calls
// Used for demo instances
define('READ_ONLY', false);
