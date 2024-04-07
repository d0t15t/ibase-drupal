<?php

namespace Drupal\price\TwigExtension;

use Drupal\price\Price;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides Price-specific Twig extensions.
 */
class PriceTwigExtension extends AbstractExtension {

  /**
   * Returns array of registered filters.
   */
  public function getFilters() {
    return [
      new TwigFilter('price_format', [$this, 'formatPrice']),
    ];
  }

  /**
   * Returns extension name.
   */
  public function getName() {
    return 'price.twig_extension';
  }

  /**
   * Formats a price object/array.
   *
   * Examples:
   * {{ order.getTotalPrice|price_format }}
   * {{ order.getTotalPrice|price_format|default('N/A') }}
   * {{ order.getTotalPrice|price_format({'minimum_fraction_digits': 0}) }}
   * {{ order.getTotalPrice|price_format({'currency_display': 'code''}) }}
   *
   * @param mixed $price
   *   Either a Price object, or an array with number and currency_code keys.
   * @param array $options
   *   (optional) An array of options to pass to the currency formatter.
   *
   * @return mixed
   *   A formatted price, suitable for rendering in a twig template.
   *
   * @throws \InvalidArgumentException
   */
  public static function formatPrice($price, array $options = []) {
    if (empty($price)) {
      return '';
    }

    if ($price instanceof Price) {
      $price = $price->toArray();
    }
    if (is_array($price) && isset($price['currency_code']) && isset($price['number'])) {
      $currency_formatter = \Drupal::service('price.currency_formatter');
      return $currency_formatter->format($price['number'], $price['currency_code'], $options);
    }
    else {
      throw new \InvalidArgumentException('The "price_format" filter must be given a price object or an array with "number" and "currency_code" keys.');
    }
  }

}
