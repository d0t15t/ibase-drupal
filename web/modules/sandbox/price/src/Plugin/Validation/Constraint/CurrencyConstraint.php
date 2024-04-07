<?php

namespace Drupal\price\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Currency constraint.
 *
 * @Constraint(
 *   id = "PriceCurrency",
 *   label = @Translation("Currency", context = "Validation"),
 *   type = { "price_price" }
 * )
 */
class CurrencyConstraint extends Constraint {

  /**
   * The array of available currencies.
   *
   * @var array
   */
  public $availableCurrencies = [];

  /**
   * The default violation message.
   *
   * @var string
   */
  public $invalidMessage = 'The currency %value is not valid.';

  /**
   * The "not available" message.
   *
   * @var string
   */
  public $notAvailableMessage = 'The currency %value is not available.';

}
