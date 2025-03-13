<?php

// phpcs:ignoreFile
//
// This is the default settings file for the MOT Drupal app.
// For a production instance, copy this file to settings.php, and make any changes as needed.

$databases = [];
$databases['default']['default'] = [
  'database' => $_ENV['DB_NAME'],
  'username' => $_ENV['DB_USER'],
  'password' => $_ENV['DB_PASS'],
  'prefix' => '',
  'host' => $_ENV['DB_HOST'],
  'port' => $_ENV['DB_PORT'],
  'isolation_level' => 'READ COMMITTED',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];

$settings['hash_salt'] = $_ENV['HASH_SALT'];
$settings['config_sync_directory'] = '../config/sync';
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['state_cache'] = TRUE;

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

$settings['trusted_host_patterns'] = [
  '^' . $_ENV['TRUSTED_HOST'] . '$',
];
