<?php

namespace Drupal\ibase_api\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ibase_api\Service\IbaseApiService;
use Drush\Commands\DrushCommands;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * A Drush command file.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class IbaseApiCommands extends DrushCommands {

  use StringTranslationTrait;

  protected $logger;

  protected MessengerInterface $messenger;

  protected IbaseApiService $apiService;

  public function __construct(
    LoggerInterface $logger,
    MessengerInterface $messenger,
    IbaseApiService $apiService,
  ) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->apiService = $apiService;
  }

  /**
   * Update Node.
   *
   * @param string $id
   *   Node ID.
   *
   * @command update:channel
   * @aliases update-channel
   *
   * @usage update:channel 96
   * @throws Exception
   */
  public function updateChannelCommand(string $id) {
    try {
      $this->logger->info($this->t('Update nodes batch operations start'));
      if ($id) {
        $this->apiService->updateChannel($id);
      }
      else {
        $this->logger->error('Channel ID is required.');
      }
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  // @TODO: transition to chunked process with feedback - move recursion into this Class?
//  public function delete(string $entity_type, $ids = null, array $options = ['bundle' => self::REQ, 'exclude' => self::REQ, 'chunks' => 50]): void
//  {
//    $query = $this->getQuery($entity_type, $ids, $options);
//    $result = $query->execute();
//
//    // Don't delete uid=1, uid=0.
//    if ($entity_type == 'user') {
//      unset($result[0], $result[1]);
//    }
//
//    if (empty($result)) {
//      $this->logger()->success(dt('No matching entities found.'));
//    } else {
//      $this->io()->progressStart(count($result));
//      foreach (array_chunk($result, $options['chunks'], true) as $chunk) {
//        drush_op([$this, 'doDelete'], $entity_type, $chunk);
//        $this->io()->progressAdvance(count($chunk));
//      }
//      $this->io()->progressFinish();
//      $this->logger()->success(dt("Deleted !type entity Ids: !ids", ['!type' => $entity_type, '!ids' => implode(', ', array_values($result))]));
//    }
//  }

}
