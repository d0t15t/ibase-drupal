#!/usr/bin/env drush
<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$bundles = ['artwork'];

$fields['field_sale_price field'] = [
  'entity_type' => 'artwork',
];

foreach ($bundles as $bundle) {
  foreach ($fields as $field_name => $config) {
    $field = FieldConfig::loadByName($config['entity_type'], $bundle, $field_name);
      $field->delete();
    if (!empty($field)) {
    }
  }
}

foreach ($fields as $field_name => $config) {
  $field_storage = FieldStorageConfig::loadByName($config['entity_type'], $field_name);
  if (!empty($field_storage)) {
    $field_storage->delete();
  }
}
