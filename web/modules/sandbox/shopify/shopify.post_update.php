<?php

/**
 * @file
 * Post update functions for Shopify.
 */

use Drupal\shopify\Batch\ShopifyProductBatch;

/**
 * Update field_shopify_collection_id field storage settings.
 */
function shopify_post_update_field_collection_id_field_storage_settings(&$sandbox) {
  $update_manager = \Drupal::entityDefinitionUpdateManager();
  $field_storage_original = $update_manager->getFieldStorageDefinition('field_shopify_collection_id', 'taxonomy_term');
  $field_storage_config_manager = \Drupal::entityTypeManager()->getStorage('field_storage_config');

  // Override settings to match updated database storage.
  // @see https://www.drupal.org/node/2535476#s-updating-field-storage-config-items
  $field_storage_original->getSetting('size', 'big');
  $field_storage_original->getSetting('unsigned', TRUE);
  $field_storage_updated = $field_storage_config_manager->create($field_storage_original->toArray());
  $field_storage_updated->original = $field_storage_updated;
  $field_storage_updated->enforceIsNew(FALSE);
  $field_storage_updated->save();
}

/**
 * Remove shopify_api module as a dependency.
 */
function shopify_post_update_remove_shopify_api_dependency(&$sandbox) {
  $shopify_api_module_settings = \Drupal::configFactory()->get('shopify_api.settings');

  // If there are previously stored API settings, migrate them.
  if (!$shopify_api_module_settings->isNew()) {
    $api_settings = \Drupal::configFactory()->getEditable('shopify.settings');
    $api_settings
      ->set('api.domain', $shopify_api_module_settings->get('domain'))
      ->set('api.key', $shopify_api_module_settings->get('api_key'))
      ->set('api.password', $shopify_api_module_settings->get('password'))
      ->set('api.secret', $shopify_api_module_settings->get('shared_secret'))
      ->save();
  }

  // Disable the shopify_api module.
  \Drupal::service('module_installer')->uninstall(['shopify_api']);
}

/**
 * Resync Shopify product options.
 */
function shopify_post_update_resync_product_options(&$sandbox) {

  if (!isset($sandbox['completed_operations'])) {
    $batch_handler = new ShopifyProductBatch();
    $batch = $batch_handler->prepare([
      'force_update' => TRUE,
      'delete_products_first' => FALSE,
      'num_per_batch' => 10,
    ])->getBatch();

    $sandbox['operations'] = $batch['operations'];
    $sandbox['completed_operations'] = 0;
    $sandbox['total_operations'] = count($sandbox['operations']);
    $sandbox['results'] = [];
    $sandbox['#finished'] = 0;
  }

  // Get the current operation and its settings.
  $operation = $sandbox['operations'][$sandbox['completed_operations']];
  $operation_method = $operation[0][1];
  $operation_settings = $operation[1][0];

  // All but the final operation will be the syncing operation.
  if ($operation_method === 'operation') {
    ShopifyProductBatch::operation($operation_settings, NULL, $sandbox);
  }
  else {
    ShopifyProductBatch::cleanUpProducts('', []);
  }

  $sandbox['completed_operations']++;
  $sandbox['#finished'] = $sandbox['completed_operations'] / ($sandbox['total_operations'] - 1);

  if ($sandbox['#finished'] >= 1) {
    ShopifyProductBatch::finished(TRUE, $sandbox['results'], NULL);
  }

}
