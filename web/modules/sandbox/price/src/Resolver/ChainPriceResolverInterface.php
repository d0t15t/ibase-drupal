<?php

namespace Drupal\price\Resolver;

/**
 * Runs the added resolvers one by one until one of them returns the price.
 *
 * Each resolver in the chain can be another chain, which is why this interface
 * extends the base price resolver one.
 */
interface ChainPriceResolverInterface extends PriceResolverInterface {

  /**
   * Adds a resolver.
   *
   * @param \Drupal\price\Resolver\PriceResolverInterface $resolver
   *   The resolver.
   */
  public function addResolver(PriceResolverInterface $resolver);

  /**
   * Gets all added resolvers.
   *
   * @return \Drupal\price\Resolver\PriceResolverInterface[]
   *   The resolvers.
   */
  public function getResolvers();

}
