<?php
// CBIT Class Monitoring System - Database Configuration
// Default settings are compatible with XAMPP on Windows.

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'cbit_class_monitoring');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CBIT Class Monitoring System');
define('APP_BASE_URL', ''); // Leave blank for normal XAMPP use.

// Upload limit reminder: PHP also follows upload_max_filesize and post_max_size in php.ini.
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
