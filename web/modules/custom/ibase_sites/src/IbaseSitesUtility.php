<?php

namespace Drupal\ibase_sites;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service description.
 */
class IbaseSitesUtility {

  public LoggerInterface $logger;

  protected MessengerInterface $messenger;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    LoggerInterface $logger,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
  }

  public function getMultiSiteName() {
    return explode('/', \Drupal\Core\DrupalKernel::findSitePath(\Drupal::request()) ?? 'default');
  }

}
