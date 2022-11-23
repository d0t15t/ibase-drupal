<?php

// @file all/settings.all.php


//$settings['file_private_path'] = '../private/' . $sites_name;
//$settings['file_temp_path'] = '../tmp/' . $sites_name;

$settings['file_private_path'] = $app_root . '/' . $site_path . '/private';
$settings['file_temp_path'] = $app_root . '/' . $site_path . '/tmp';

$settings['container_yamls'][] = $app_root . '/sites/all/development.services.yml';

$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

$settings['cache']['bins']['page'] = 'cache.backend.null';

$settings['extension_discovery_scan_tests'] = FALSE;

$config['system.logging']['error_level'] = 'verbose';

