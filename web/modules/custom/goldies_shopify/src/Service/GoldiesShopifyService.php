<?php

namespace Drupal\goldies_shopify\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class GoldiesShopifyService.
 */
class GoldiesShopifyService {

  const FIELD_SHOPIFY_COLLECTION_ID = 'field_shopify_collection_id';

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GoldiesShopifyService constructor.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function syncCollection(EntityInterface $entity) {
    switch ($entity->bundle()) {
      case 'shopify_collections':
        $shopifyHandle = $entity->get('field_shopify_handle')->getString();
        $collectionResults = $this
          ->entityTypeManager
          ->getStorage('node')
          ->loadByProperties(['field_shopify_handle' => $shopifyHandle]);
        $nodeStorage = $this->entityTypeManager->getStorage('node');
        $collection = empty($collectionResults)
          ? $nodeStorage->create(['type' => 'collection'])
          : reset($collectionResults);
        $collection
          ->set('title', $entity->label())
          ->set('field_shopify_handle', $entity->get('field_shopify_handle')->getString())
          ->set('field_shopify_collection', ['target_id' => $entity->id()])
          ->save();
        break;
    }
  }

  public function syncProduct(EntityInterface $entity) {
    $shopifyHandle = $entity->get('field_shopify_handle')->getString();
    $productResults = $this
      ->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_shopify_handle' => $shopifyHandle]);
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $collection = empty($collectionResults)
      ? $nodeStorage->create(['type' => 'product'])
      : reset($productResults);
    $collection
      ->set('title', $entity->label())
      ->set('field_shopify_handle', $entity->get('field_shopify_handle')->getString())
      ->set('field_shopify_product', ['target_id' => $entity->id()])
      ->save();
  }

  // @todo: delete sequence for removed shopify-products/collections.

  public function syncShopifyNode(EntityInterface $entity, $targetBundle) {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $referenceField = sprintf('field_shopify_%s', $targetBundle);
    $nodes = $nodeStorage->loadByProperties([$referenceField => ['target_id' => $entity->id()]]);
    if (empty($nodes)) {
      $nodeStorage
        ->create(['type' => $targetBundle])
        ->set('title', $entity->label())
        ->set($referenceField, ['target_id' => $entity->id()])
        ->save();
    }
  }

}
