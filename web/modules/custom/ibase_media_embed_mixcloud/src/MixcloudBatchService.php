<?php

namespace Drupal\ibase_media_embed_mixcloud;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ibase_media_embed_mixcloud\Service\EmbedMixcloudService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BatchService.
 */
class BatchService implements ContainerInjectionInterface {

  use StringTranslationTrait;

  protected EmbedMixcloudService $mixcloud;

  public function __construct(EmbedMixcloudService $mixcloud) {
    $this->mixcloud = $mixcloud;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ibase_media_embed_mixcloud.service')
    );
  }

  /**
   * Batch process callback.
   *
   * @param int $id
   *   Id of the batch.
   * @param string $operation_details
   *   Details of the operation.
   * @param object $context
   *   Context for operations.
   */
  public function processChannel($id, $operation_details, &$context) {
    // Simulate long process by waiting 100 microseconds.
    usleep(100);
    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $id;
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details', [
      '@id' => $id,
      '@details' => $operation_details,
    ]);
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public function processMyNodeFinished($success, array $results, array $operations) {
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $this->messenger->addMessage(t('@count results processed.', [
        '@count' => count($results),
      ]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $this->messenger->addError(t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]));
    }
  }

}
