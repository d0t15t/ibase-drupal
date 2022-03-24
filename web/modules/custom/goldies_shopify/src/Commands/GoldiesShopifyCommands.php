<?php

namespace Drupal\goldies_shopify\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\node\Entity\Node;
use Drupal\shopify\Entity\ShopifyProduct;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class GoldiesShopifyCommands extends DrushCommands {

  /**
   * Sync Shopify Products
   *
   * @return RowsOfFields
   * @throws EntityMalformedException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @field-labels
   *   product_name: Product Name
   *   product_id: Product ID
   *   node_id: Node ID
   *   node_path: Node Path
   * @default-fields product_name,product_id,node_id,node_path
   *
   * @command goldies_shopify:sync_products
   * @aliases gssp
   *
   * @filter-default-field product_name
   */
  public function syncShopifyProducts(): RowsOfFields {
    $product_entity_type = 'shopify_product';
    $storage = \Drupal::entityTypeManager()->getStorage($product_entity_type);
    $products = \Drupal::entityQuery($product_entity_type)->execute();
    $rows = [];
    foreach ($products as $product_id) {
      /** @var ShopifyProduct $product */
      $product = $storage->load($product_id);
      /** @var Node $node */
      $node = \Drupal::service('goldies_shopify.utility')->syncShopifyNode($product, 'product');
      $t=1;
      $rows[] = [
        'product_name' => $product->label(),
        'product_id' => $product->id(),
        'node_id' => $node->id(),
        'node_path' => $node->toUrl()->toString(),
      ];
    }
    return new RowsOfFields($rows);
  }

}
