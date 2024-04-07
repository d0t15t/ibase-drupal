<?php

namespace Drupal\price;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swaps the default physical.number_formatter service class.
 */
class PriceServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('physical.number_formatter')) {
      $container->getDefinition('physical.number_formatter')
        ->setClass(PhysicalNumberFormatter::class)
        ->setArguments([new Reference('price.number_format_repository'), new Reference('price.current_locale')]);
    }
  }

}
