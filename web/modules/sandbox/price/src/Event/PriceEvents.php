<?php

namespace Drupal\price\Event;

/**
 * Defines events for the price module.
 */
final class PriceEvents {

  /**
   * Name of the event fired when altering a number format.
   *
   * @Event
   *
   * @see \Drupal\price\Event\NumberFormatDefinitionEvent
   */
  const NUMBER_FORMAT = 'price.number_format';

}
