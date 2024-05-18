<?php

/**
 * @file
 * @var string $app_root
 * @var string $site_path
 */

// come up with a convinceing hash_salt value
$settings['hash_salt'] = 'sh3';

$settings['file_private_path'] = "$app_root/$site_path/private";

$settings['file_temp_path'] = "$app_root/$site_path/tmp";

$settings['ibase_external_content_url'] = 'https://thegallery.art/jsonapi/node/artwork';

if (getenv('IS_DDEV_PROJECT') == 'true') {

  $settings['container_yamls'][] = $app_root . '/sites/development.services.yml';

  $config['system.performance']['css']['preprocess'] = FALSE;

  $config['system.performance']['js']['preprocess'] = FALSE;

  $settings['cache']['bins']['render'] = 'cache.backend.null';

  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

  $settings['cache']['bins']['page'] = 'cache.backend.null';

  $settings['extension_discovery_scan_tests'] = FALSE;

  $config['system.logging']['error_level'] = 'verbose';

  $settings['config_exclude_modules'] = ['devel', 'stage_file_proxy'];

  $databases['default']['default'] = [
    'database' => 'db',
    'username' => 'root',
    'password' => 'root',
    'prefix' => '',
    'host' => 'db',
    'port' => '3306',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => 'mysql',
  ];

}
