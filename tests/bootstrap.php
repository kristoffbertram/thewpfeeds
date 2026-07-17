<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// WP time constants used by the plugin (normally defined by WordPress core).
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

define('THEWPFEEDS_TESTS_DIR', __DIR__);
define('THEWPFEEDS_FIXTURES_DIR', dirname(__DIR__) . '/data/fixtures');
