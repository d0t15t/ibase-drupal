<?php

namespace Drupal\price\Resolver;

use Drupal\price\Context;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for price resolvers.
 */
interface PriceResolverInterface {

  /**
   * Resolves a price for the given purchasable entity.
   *
   * Use $context->getData('field_name', 'price') to get the name of the field
   * for which the price is being resolved (e.g "price").
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $quantity
   *   The quantity.
   * @param \Drupal\price\Context $context
   *   The context.
   *
   * @return \Drupal\price\Price|null
   *   A price value object, if resolved. Otherwise NULL, indicating that the
   *   next resolver in the chain should be called.
   */
  public function resolve(ContentEntityInterface $entity, $quantity, Context $context);

}
