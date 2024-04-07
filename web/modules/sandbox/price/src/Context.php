<?php

namespace Drupal\price;

use Drupal\Core\Session\AccountInterface;

/**
 * Contains known global information (user, time).
 *
 * Passed to price resolvers and availability checkers.
 */
final class Context {

  /**
   * The user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The time.
   *
   * @var int
   */
  protected $time;

  /**
   * The data.
   *
   * Used to provide additional information for a specific set of users
   * (e.g. price resolvers).
   *
   * @var array
   */
  protected $data;

  /**
   * Constructs a new Context object.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param int|null $time
   *   The unix timestamp, or NULL to use the current time.
   * @param array $data
   *   The data.
   */
  public function __construct(AccountInterface $user, int $time = NULL, array $data = []) {
    $this->user = $user;
    $this->time = $time ?: time();
    $this->data = $data;
  }

  /**
   * Gets the user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The user.
   */
  public function getUser() : AccountInterface {
    return $this->user;
  }

  /**
   * Gets the time.
   *
   * @return int
   *   The time.
   */
  public function getTime() : int {
    return $this->time;
  }

  /**
   * Gets a data value with the given key.
   *
   * @param string $key
   *   The key.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The value.
   */
  public function getData(string $key, $default = NULL) {
    return isset($this->data[$key]) ? $this->data[$key] : $default;
  }

}
