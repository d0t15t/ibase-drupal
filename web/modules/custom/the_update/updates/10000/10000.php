#!/usr/bin/env drush
<?php

/**
 * @file
 * Install, update and uninstall functions for the THE Update module.
 */

$module_handler = \Drupal::service('module_handler');

$entity_type_manager = \Drupal::entityTypeManager();

$module_path = $module_handler->getModule('the_update')->getPath();

$dir = "$module_path/updates/10000/data";

$files = scandir($dir);

foreach ($files as $file) {
  $lines = [];
  $file_parts = pathinfo($file);
  if ($file_parts['extension'] == 'csv') {
    if (($handle = fopen($dir . '/' . $file, 'r')) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $lines[] = $data;
      }
      $field_name = $file_parts['filename'];
      $node_storage = $entity_type_manager->getStorage('node');
      $cols = array_shift($lines);
      $count = count($lines);
      foreach ($lines as $i => $line) {

        try {
          [$bundle, $nid, $lang, $price, $currency] = $line;
          $node = $node_storage->load($nid);
          if ($node && $node->hasField($field_name)) {
            echo "Updating $i node $nid with $field_name of $count\n";
            $node->set($field_name, [
              'number' => $price,
              'currency_code' => $currency,
            ]);
            $node->save();
          } else {
            echo "Node $nid not found or field $field_name not found\n";
            return 1;
          }
        } catch (\Exception $e) {
          echo $e->getMessage();
        }
      }
      fclose($handle);
    }
  }
}
