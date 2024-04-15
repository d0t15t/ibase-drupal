<?php

declare(strict_types=1);

namespace Drupal\the_pager;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;

/**
 * @todo Add class description.
 */
final class ThePagerUtility {

  /**
   * Constructs a ThePagerUtility object.
   */
  public function __construct(
    private readonly PagerManagerInterface $pagerManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
  ) {}

  /**
   * @todo Add method description.
   */
  public function pagerQuery(): ?array {
    // @todo Place your code here.
  }

}
