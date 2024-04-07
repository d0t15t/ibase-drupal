<?php

namespace Drupal\price\Resolver;

use Drupal\price\Context;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Default implementation of the chain base price resolver.
 */
class ChainPriceResolver implements ChainPriceResolverInterface {

  /**
   * The resolvers.
   *
   * @var \Drupal\price\Resolver\PriceResolverInterface[]
   */
  protected $resolvers = [];

  /**
   * Constructs a new ChainBasePriceResolver object.
   *
   * @param \Drupal\price\Resolver\PriceResolverInterface[] $resolvers
   *   The resolvers.
   */
  public function __construct(array $resolvers = []) {
    $this->resolvers = $resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function addResolver(PriceResolverInterface $resolver) {
    $this->resolvers[] = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolvers() {
    return $this->resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(ContentEntityInterface $entity, $quantity, Context $context) {
    foreach ($this->resolvers as $resolver) {
      $result = $resolver->resolve($entity, $quantity, $context);
      if ($result) {
        return $result;
      }
    }
  }

}
