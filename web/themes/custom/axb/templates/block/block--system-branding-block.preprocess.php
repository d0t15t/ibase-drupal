<?php

/**
 * @var $vars array
 */

try {
  $vars['is_front'] = \Drupal::service('path.matcher')->isFrontPage() ?? NULL;
} catch (Exception $e) {
  $vars['is_front'] = NULL;
}