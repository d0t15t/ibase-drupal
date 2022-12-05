<?php

namespace Drupal\ibase_mixcloud_media\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ibase_mixcloud_media\Service\MixcloudMediaService;
use Drush\Commands\DrushCommands;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MixcloudMediaCommands extends DrushCommands {

  use StringTranslationTrait;

  protected MixcloudMediaService $mixcloud;

  public function __construct(
    MixcloudMediaService $mixcloud
  ) {
    $this->mixcloud = $mixcloud;
  }

  /**
   * Update Channel Node.
   * @param string $nid
   *
   * @field-labels
   *   nid: Batched NID
   *   name: Episode name
   * @default-fields nid,name
   *
   * @command update:channel
   * @aliases update-channel
   * @usage update:channel 96
   *
   * @filter-default-field page
   * @return RowsOfFields
   * @throws Exception
   */
  public function updateChannelCommand(string $nid): RowsOfFields {
//  public function updateChannelCommand(OutputInterface $output, $nid = 1093): RowsOfFields {
//  public function updateChannelCommand(string $nid): RowsOfFields {
    try {
      if ($nid) {
        if ($channel = $this->mixcloud->getValidChannel($nid)) {
          // Start.
          $prepared_message = sprintf('############## "%s" - processing Mixcloud channel ############', $channel->label());
          $this->logger->info($prepared_message);
          $this->output()->writeln($prepared_message);

          // Get data && normalize it.
          $endpoint = $this->mixcloud->getChannelEndpoint($channel);
          $channel_store_label = $this->mixcloud->saveChannelPagedData($endpoint);
          $channel_store_data = $this->mixcloud->getTempstoreData($channel_store_label);
          list('paged_data' => $channel_paged_data, 'items_count' => $channel_items_count) = $channel_store_data;
          // $channel_store_data contains incomplete data about the episodes,
          // access the endpoints individually to get full data.
          $channel_episode_urls = $this->mixcloud->getChannelEpisodesEndpoints($channel_paged_data);
          $channel_episode_endpoints = array_map(fn ($e) => $this->mixcloud->modifyEpisodeEndpoint($e), $channel_episode_urls);

          // Report prepared data.
          $prepared_message = sprintf('Begin batch consumption of %s items.', $channel_items_count);
          $this->logger->info($prepared_message);
          $this->output()->writeln($prepared_message);

          $batchBuilder = new BatchBuilder();
          $batchBuilder
            ->setTitle($this->t('Run batch for %num items.', ['%num' => $channel_items_count,]))
            ->setFinishCallback([MixcloudMediaService::class, 'batchProcessEpisodesFinished'])
            ->setErrorMessage(t('Batch has encountered an error.'));

          foreach ($channel_episode_endpoints as $i => $endpoint) {
//            if ($i > 0) continue;
            $batchId = $i + 1;
            $batchBuilder->addOperation([MixcloudMediaService::class, 'batchProcessEpisodeEndpoint'], [
              $endpoint,
              $batchId,
              $channel->id(),
            ]);
          }

          batch_set($batchBuilder->toArray());
          $done = drush_backend_batch_process();

          $episode_ids = array_map(fn($e)=> $e['id'], $done[0]);
          $channel->set('field_episodes', $episode_ids);
          $channel->save();

          $rows = array_map(function ($e) {
            return [
              'nid' => $e['id'],
              'name' => $e['title'],
            ];
          }, $done[0]);

          return new RowsOfFields($rows);
        }
      }
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  /**
   * Main command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   *
   * @command migrate_opm:progress-bar
   * @aliases opm-insert-users
   * @usage migrate_opm:insert-users
   */
  public function show_progress_bar(InputInterface $input, OutputInterface $output) {
    $progressBar = new ProgressBar($output, 100);
    $progressBar->start();
    for ($i = 1; $i <= 100; $i++) {
      usleep(59);
      $progressBar->advance();
    }
    $progressBar->finish();
  }

}
