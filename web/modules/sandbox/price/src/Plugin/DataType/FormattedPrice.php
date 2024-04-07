<?php

namespace Drupal\price\Plugin\DataType;

use Drupal\price\Plugin\Field\FieldType\PriceItem;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * Defines a data type for formatted prices.
 *
 * @DataType(
 *   id = "formatted_price",
 *   label = @Translation("Formatted price")
 * )
 */
class FormattedPrice extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $parent = $this->getParent();
    assert($parent instanceof PriceItem);
    $formatted_price = NULL;
    if (!$parent->isEmpty()) {
      try {
        $price = $parent->toPrice();
        $currency_formatter = \Drupal::service('price.currency_formatter');
        $formatted_price = $currency_formatter->format($price->getNumber(), $price->getCurrencyCode());
      }
      catch (\InvalidArgumentException $exception) {

      }
    }

    return $formatted_price;
  }

}
