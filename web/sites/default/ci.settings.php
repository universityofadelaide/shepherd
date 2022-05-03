<?php

/**
 * @file
 * Provides settings used in automated testing.
 */

$config['system.logging']['error_level'] = 'verbose';

$settings['extension_discovery_scan_tests'] = TRUE;

$databases['default']['default'] = [
  'driver' => 'sqlite',
  'database' => getenv('SQLITE_DATABASE'),
];
